<?php

namespace Tests\Feature;

use App\Models\AnaliticoUnimedLinha;
use App\Models\AnaliticoUnimedLote;
use App\Models\ConciliacaoFinanceira;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\TabelaValor;
use App\Models\Tenant;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioFinanceiroService;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Os números da aba Financeira.
 *
 * Dois pontos concentram o risco desta aba e têm teste próprio: o valor
 * executado, que precisa usar a tabela de preços vigente na DATA DA SESSÃO (e
 * não a de hoje), e a distinção entre "não houve glosa" e "ninguém importou o
 * analítico".
 */
class RelatorioFinanceiroServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const DE = '2026-09-01';

    private const ATE = '2026-09-30';

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $agora = CarbonImmutable::parse('2026-09-20 09:00:00', RelatorioPeriodo::FUSO);
        Carbon::setTestNow($agora);
        CarbonImmutable::setTestNow($agora);

        $this->tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($this->tenantId);

        $this->limpar();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ── Valor executado ─────────────────────────────────────────────────────

    public function test_executado_soma_sessoes_realizadas_pelo_valor_da_tabela(): void
    {
        $this->valorDoConvenio('100.00', '2026-01-01');

        $guia = $this->guia();
        $this->sessao($guia, '2026-09-05', 'completed');
        $this->sessao($guia, '2026-09-06', 'completed');
        // Falta e cancelada não são executadas.
        $this->sessao($guia, '2026-09-07', 'missed');
        $this->sessao($guia, '2026-09-08', 'canceled');
        // Fora do período.
        $this->sessao($guia, '2026-08-20', 'completed');

        $this->assertSame(20_000, $this->kpis()['valor_executado']['valor'], 'dois × R$ 100,00 em centavos');
    }

    /**
     * O defeito que este teste existe para impedir: usar o preço de HOJE para
     * uma sessão de meses atrás. A tabela subiu de R$ 100 para R$ 150 no dia 10;
     * a sessão do dia 5 continua valendo R$ 100.
     */
    public function test_executado_usa_o_valor_vigente_na_data_da_sessao(): void
    {
        $this->valorDoConvenio('100.00', '2026-01-01', '2026-09-09');
        $this->valorDoConvenio('150.00', '2026-09-10');

        $guia = $this->guia();
        $this->sessao($guia, '2026-09-05', 'completed');
        $this->sessao($guia, '2026-09-15', 'completed');

        $this->assertSame(25_000, $this->kpis()['valor_executado']['valor']);
    }

    /** A linha mais específica ganha: convênio + especialidade + profissional. */
    public function test_a_cascata_prefere_a_linha_mais_especifica(): void
    {
        $guia = $this->guia();

        $this->valorDoConvenio('100.00', '2026-01-01');
        $this->valor('130.00', '2026-01-01', especialidadeId: $guia->especialidade_id);
        $this->valor('170.00', '2026-01-01', especialidadeId: $guia->especialidade_id, profissionalId: $guia->profissional_id);

        $this->sessao($guia, '2026-09-05', 'completed');

        $this->assertSame(17_000, $this->kpis()['valor_executado']['valor']);
    }

    public function test_repasse_estimado_usa_o_percentual_do_profissional(): void
    {
        $this->valorDoConvenio('100.00', '2026-01-01');

        $guia = $this->guia();
        $guia->profissional->forceFill(['percentual_repasse' => '60.00'])->save();

        $this->sessao($guia, '2026-09-05', 'completed');
        $this->sessao($guia, '2026-09-06', 'completed');

        $this->assertSame(12_000, $this->kpis()['repasse_estimado']['valor'], '60% de R$ 200,00');
    }

    // ── Números da operadora ────────────────────────────────────────────────

    public function test_apresentado_e_a_soma_de_pago_com_glosado(): void
    {
        $this->lote('2026-09-10 10:00:00', pago: '8000.00', glosado: '2000.00');

        $kpis = $this->kpis();

        $this->assertSame(1_000_000, $kpis['valor_apresentado']['valor']);
        $this->assertSame(800_000, $kpis['valor_pago']['valor']);
        $this->assertSame(200_000, $kpis['valor_glosado']['valor']);
        $this->assertSame(20.0, $kpis['taxa_glosa']['valor']);
    }

    /**
     * Sem lote no período, os três voltam ausentes.
     *
     * Zero afirmaria que a operadora não pagou nada; o que houve foi ninguém ter
     * importado o analítico ainda. É a distinção que a spec exige.
     */
    public function test_sem_lote_no_periodo_os_indicadores_sao_ausentes_e_nao_zero(): void
    {
        $this->lote('2026-08-10 10:00:00', pago: '8000.00', glosado: '2000.00');

        $kpis = $this->kpis();

        $this->assertNull($kpis['valor_apresentado']['valor']);
        $this->assertNull($kpis['valor_pago']['valor']);
        $this->assertNull($kpis['valor_glosado']['valor']);
        $this->assertNull($kpis['taxa_glosa']['valor']);
    }

    /** Lote sem glosa nenhuma é zero de glosa — aí sim. */
    public function test_lote_sem_glosa_e_zero_e_nao_ausente(): void
    {
        $this->lote('2026-09-10 10:00:00', pago: '5000.00', glosado: '0.00');

        $kpis = $this->kpis();

        $this->assertSame(0, $kpis['valor_glosado']['valor']);
        $this->assertSame(0.0, $kpis['taxa_glosa']['valor']);
    }

    public function test_pareto_de_glosa_agrupa_por_motivo(): void
    {
        $lote = $this->lote('2026-09-10 10:00:00', pago: '1000.00', glosado: '300.00');

        $this->linhaDeGlosa($lote, 'Guia sem autorização', '200.00');
        $this->linhaDeGlosa($lote, 'Guia sem autorização', '50.00');
        $this->linhaDeGlosa($lote, 'Procedimento não coberto', '50.00');

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'glosa_por_motivo');

        $this->assertSame(
            [['x' => 'Guia sem autorização', 'total' => 25_000], ['x' => 'Procedimento não coberto', 'total' => 5_000]],
            $serie['pontos'],
        );
    }

    // ── Conciliações ────────────────────────────────────────────────────────

    public function test_situacao_das_conciliacoes(): void
    {
        $guia = $this->guia();

        $this->conciliacao($guia, 'pending');
        $this->conciliacao($guia, 'pending');
        $this->conciliacao($guia, 'reviewed');
        $this->conciliacao($guia, 'paid');

        $kpis = $this->kpis();

        $this->assertSame(2, $kpis['conciliacoes_pendentes']['valor']);
        $this->assertSame(1, $kpis['conciliacoes_revisadas']['valor']);
        $this->assertSame(1, $kpis['conciliacoes_pagas']['valor']);

        $pizza = collect($this->relatorio()['series'])->firstWhere('key', 'situacao_das_conciliacoes');

        $this->assertSame(['Pendentes', 'Conferidas', 'Pagas'], array_column($pizza['pontos'], 'x'));
    }

    // ── Comparação e isolamento ─────────────────────────────────────────────

    public function test_o_executado_do_periodo_anterior_e_calculado(): void
    {
        $this->valorDoConvenio('100.00', '2026-01-01');

        $guia = $this->guia();
        $this->sessao($guia, '2026-09-05', 'completed');
        // O anterior de setembro (30 dias) é 2 a 31 de agosto.
        $this->sessao($guia, '2026-08-20', 'completed');
        $this->sessao($guia, '2026-08-25', 'completed');

        $kpi = $this->kpis(comparar: true)['valor_executado'];

        $this->assertSame(10_000, $kpi['valor']);
        $this->assertSame(20_000, $kpi['anterior']);
    }

    public function test_nao_soma_analitico_de_outra_clinica(): void
    {
        $this->lote('2026-09-10 10:00:00', pago: '1000.00', glosado: '0.00');

        $vizinha = Tenant::factory()->create();
        AnaliticoUnimedLote::query()->create([
            'tenant_id' => $vizinha->id,
            'arquivo_nome_original' => 'vizinha.xlsx',
            'status' => 'importado',
            'importado_em' => '2026-09-11 10:00:00',
            'total_pago' => '9999.00',
            'total_glosado' => '0.00',
        ]);

        $this->assertSame(100_000, $this->kpis()['valor_pago']['valor']);

        Cache::flush();

        $todas = app(RelatorioFinanceiroService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: null,
        ));

        $this->assertSame(
            1_099_900,
            collect($todas['kpis'])->firstWhere('key', 'valor_pago')['valor'],
            'todas as clínicas somam as duas',
        );
    }

    // ── Contrato ────────────────────────────────────────────────────────────

    public function test_series_e_tabelas_tem_as_chaves_que_a_tela_espera(): void
    {
        $relatorio = $this->relatorio();

        $this->assertSame(
            ['executado_x_pago', 'glosa_por_motivo', 'repasse_por_profissional', 'situacao_das_conciliacoes'],
            array_column($relatorio['series'], 'key'),
        );

        $this->assertSame(
            ['por_profissional', 'por_convenio', 'glosa_por_motivo'],
            array_column($relatorio['tabelas'], 'key'),
        );
    }

    /** Todo indicador de dinheiro sai em centavos inteiros. */
    public function test_dinheiro_sai_em_centavos_inteiros(): void
    {
        $this->valorDoConvenio('99.90', '2026-01-01');
        $this->sessao($this->guia(), '2026-09-05', 'completed');

        $valor = $this->kpis()['valor_executado']['valor'];

        $this->assertIsInt($valor);
        $this->assertSame(9_990, $valor);
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /** @return array<string, array<string, mixed>> */
    private function kpis(bool $comparar = false): array
    {
        return collect($this->relatorio($comparar)['kpis'])->keyBy('key')->all();
    }

    /** @return array<string, mixed> */
    private function relatorio(bool $comparar = false): array
    {
        return app(RelatorioFinanceiroService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $this->tenantId,
            comparar: $comparar,
        ));
    }

    private function guia(): Guia
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'FIN-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::FINALIZED,
            'data_solicitacao' => '2026-09-01',
        ]);

        return $guia->load('profissional');
    }

    private function sessao(Guia $guia, string $dia, string $status): void
    {
        Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => $dia,
            'status' => $status,
        ]);
    }

    private function valorDoConvenio(string $valor, string $desde, ?string $ate = null): void
    {
        $this->valor($valor, $desde, $ate);
    }

    private function valor(
        string $valor,
        string $desde,
        ?string $ate = null,
        ?int $especialidadeId = null,
        ?int $profissionalId = null,
    ): void {
        TabelaValor::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => Convenio::query()->where('nome', 'Unimed')->firstOrFail()->id,
            'especialidade_id' => $especialidadeId,
            'profissional_id' => $profissionalId,
            'valor' => $valor,
            'vigente_desde' => $desde,
            'vigente_ate' => $ate,
        ]);
    }

    private function lote(string $importadoEm, string $pago, string $glosado): AnaliticoUnimedLote
    {
        return AnaliticoUnimedLote::query()->create([
            'tenant_id' => $this->tenantId,
            'arquivo_nome_original' => 'analitico.xlsx',
            'status' => 'importado',
            'importado_em' => $importadoEm,
            'total_pago' => $pago,
            'total_glosado' => $glosado,
        ]);
    }

    private function linhaDeGlosa(AnaliticoUnimedLote $lote, string $motivo, string $valor): void
    {
        AnaliticoUnimedLinha::query()->create([
            'tenant_id' => $this->tenantId,
            'analitico_unimed_lote_id' => $lote->id,
            'linha' => 1,
            'origem' => 'glosa',
            'natureza' => 'glosado',
            'motivo' => $motivo,
            'valor_normalizado' => $valor,
        ]);
    }

    private function conciliacao(Guia $guia, string $status): void
    {
        ConciliacaoFinanceira::query()->create([
            'tenant_id' => $this->tenantId,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => 1,
            'valor_unitario' => '100.00',
            'valor_total' => '100.00',
            'status' => $status,
            'created_at' => '2026-09-10 10:00:00',
        ]);
    }

    /** O seeder traz dados próprios; os testes de número partem do zero. */
    private function limpar(): void
    {
        ConciliacaoFinanceira::query()->withoutGlobalScopes()->delete();
        Lancamento::query()->withoutGlobalScopes()->delete();
        AnaliticoUnimedLinha::query()->withoutGlobalScopes()->delete();
        AnaliticoUnimedLote::query()->withoutGlobalScopes()->delete();
        TabelaValor::query()->withoutGlobalScopes()->delete();
        GuiaStatusHistorico::query()->withoutGlobalScopes()->delete();
        Guia::query()->withoutGlobalScopes()->delete();
    }
}
