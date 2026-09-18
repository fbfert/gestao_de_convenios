<?php

namespace Tests\Feature;

use App\Models\Antecipacao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioOperacaoService;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Os números da aba de Operação, com valores conhecidos.
 *
 * Tudo aqui é montado sobre uma data fixa e transições escritas à mão em
 * `guia_status_historico`: é a única forma de provar que o relatório conta pela
 * data do FATO e não pela data de criação da guia — que é o defeito que esta
 * aba existe para não cometer.
 */
class RelatorioOperacaoServiceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ── Decisões pela data da transição ─────────────────────────────────────

    public function test_tres_aprovadas_e_uma_negada_dao_75_por_cento(): void
    {
        $this->limparHistorico();

        foreach (['2026-09-05', '2026-09-10', '2026-09-15'] as $dia) {
            $this->guiaDecidida(GuiaStatus::APPROVED, $dia);
        }

        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-20');

        $kpis = $this->kpis();

        $this->assertSame(75.0, $kpis['taxa_aprovacao']['valor']);
        $this->assertSame(25.0, $kpis['taxa_negacao']['valor']);
    }

    /** Guia nascida em agosto e negada em setembro é negação de SETEMBRO. */
    public function test_guia_criada_antes_e_decidida_dentro_conta_no_periodo(): void
    {
        $this->limparHistorico();

        $guia = $this->guia(['created_at' => '2026-08-10 08:00:00']);
        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-08-10 08:00:00');
        $this->transicao($guia, GuiaStatus::DENIED, '2026-09-12 08:00:00');

        $kpis = $this->kpis();

        $this->assertSame(0.0, $kpis['taxa_aprovacao']['valor']);
        $this->assertSame(100.0, $kpis['taxa_negacao']['valor']);
        // E ela NÃO entra em "guias geradas", que é evento de criação.
        $this->assertSame(0, $kpis['guias_geradas']['valor']);
    }

    /** E a criada em setembro, decidida em outubro, não conta entre as decisões. */
    public function test_guia_criada_dentro_e_decidida_depois_nao_conta(): void
    {
        $this->limparHistorico();

        $guia = $this->guia(['created_at' => '2026-09-12 08:00:00']);
        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-12 08:00:00');
        $this->transicao($guia, GuiaStatus::APPROVED, '2026-10-02 08:00:00');

        $kpis = $this->kpis();

        $this->assertSame(1, $kpis['guias_geradas']['valor']);
        $this->assertNull($kpis['taxa_aprovacao']['valor'], 'nenhuma guia decidida no período');
    }

    /** Sem guia decidida, a taxa é ausente — e não zero, que afirmaria "nada foi aprovado". */
    public function test_sem_decisao_a_taxa_e_ausente_e_nao_zero(): void
    {
        $this->limparHistorico();

        $kpis = $this->kpis();

        $this->assertNull($kpis['taxa_aprovacao']['valor']);
        $this->assertNull($kpis['taxa_negacao']['valor']);
    }

    /** Duas negações da mesma guia no mesmo período são uma guia negada. */
    public function test_a_mesma_guia_negada_duas_vezes_conta_uma_vez(): void
    {
        $this->limparHistorico();

        $guia = $this->guia();
        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-02 08:00:00');
        $this->transicao($guia, GuiaStatus::DENIED, '2026-09-03 08:00:00');
        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-04 08:00:00');
        $this->transicao($guia, GuiaStatus::DENIED, '2026-09-05 08:00:00');

        $this->assertSame(100.0, $this->kpis()['taxa_negacao']['valor']);
    }

    // ── Tempos ──────────────────────────────────────────────────────────────

    public function test_tempo_medio_e_mediano_ate_a_decisao(): void
    {
        $this->limparHistorico();

        // 2h, 4h e 12h: média 6h, mediana 4h.
        foreach ([['08:00', '10:00'], ['08:00', '12:00'], ['08:00', '20:00']] as $i => [$entrada, $decisao]) {
            $dia = '2026-09-0'.($i + 2);
            $guia = $this->guia();
            $this->transicao($guia, GuiaStatus::UNDER_REVIEW, "{$dia} {$entrada}:00");
            $this->transicao($guia, GuiaStatus::APPROVED, "{$dia} {$decisao}:00");
        }

        $kpis = $this->kpis();

        $this->assertSame(6.0, $kpis['tempo_medio_decisao']['valor']);
        $this->assertSame(4.0, $kpis['tempo_mediano_decisao']['valor']);
    }

    public function test_tempo_medio_ate_a_aprovacao_sai_de_autorizada_para_aprovada(): void
    {
        $this->limparHistorico();

        $guia = $this->guia();
        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-02 08:00:00');
        $this->transicao($guia, GuiaStatus::APPROVED, '2026-09-02 10:00:00');
        $this->transicao($guia, GuiaStatus::FINALIZED, '2026-09-03 10:00:00');

        $this->assertSame(24.0, $this->kpis()['tempo_medio_finalizacao']['valor']);
    }

    public function test_sem_decisao_o_tempo_e_ausente(): void
    {
        $this->limparHistorico();

        $this->assertNull($this->kpis()['tempo_medio_decisao']['valor']);
    }

    // ── Sessões ─────────────────────────────────────────────────────────────

    public function test_conta_sessoes_realizadas_faltas_e_canceladas(): void
    {
        $this->limparHistorico();
        $this->limparSessoes();

        $guia = $this->guia();

        foreach (['completed', 'completed', 'missed', 'canceled'] as $i => $status) {
            $this->sessao($guia, '2026-09-1'.$i, $status);
        }

        // Fora do período: não pode entrar em nenhum dos três.
        $this->sessao($guia, '2026-08-15', 'completed');

        $kpis = $this->kpis();

        $this->assertSame(2, $kpis['sessoes_realizadas']['valor']);
        $this->assertSame(1, $kpis['sessoes_faltas']['valor']);
        $this->assertSame(1, $kpis['sessoes_canceladas']['valor']);
    }

    // ── Senhas a vencer ─────────────────────────────────────────────────────

    /**
     * O prazo vem de `configuracoes_globais`, nunca de um número em código: com
     * a janela em 5 dias, a senha que vence em 10 fica de fora; ampliando a
     * configuração para 15, ela entra.
     */
    public function test_senhas_a_vencer_usam_a_janela_configurada_da_clinica(): void
    {
        $this->limparHistorico();

        $config = ConfiguracaoGlobal::doTenant($this->tenantId);
        $config->forceFill(['senha_alerta_dias' => 5])->save();

        Guia::query()->update(['validade_senha' => null]);

        // Hoje é 2026-09-20 (fixado no setUp).
        $this->guia(['validade_senha' => '2026-09-23']);
        $this->guia(['validade_senha' => '2026-09-30']);

        $this->assertSame(1, $this->kpis()['senhas_vencendo']['valor']);

        $config->forceFill(['senha_alerta_dias' => 15])->save();

        // O recorte não mudou, então a resposta anterior ainda está no cache de
        // 5 minutos. Limpar é o que faz este teste medir a configuração nova, e
        // não o reaproveitamento — que tem teste próprio em RelatoriosApiTest.
        Cache::flush();

        $this->assertSame(2, $this->kpis()['senhas_vencendo']['valor']);
    }

    /** É um retrato do agora: na comparação com o período anterior não existe valor. */
    public function test_senhas_a_vencer_nao_tem_periodo_anterior(): void
    {
        $this->limparHistorico();
        $this->guia(['validade_senha' => '2026-09-21']);

        $kpi = $this->kpis(comparar: true)['senhas_vencendo'];

        $this->assertSame(1, $kpi['valor']);
        $this->assertNull($kpi['anterior']);
    }

    // ── Antecipações ────────────────────────────────────────────────────────

    public function test_antecipacoes_geradas_ignoradas_e_percentual_de_dispensa(): void
    {
        $this->limparHistorico();

        $solicitacao = $this->solicitacao();

        $this->antecipacao($solicitacao, Antecipacao::STATUS_GERADA, ['gerado_em' => '2026-09-05 10:00:00']);
        $this->antecipacao($solicitacao, Antecipacao::STATUS_GERADA, ['gerado_em' => '2026-09-06 10:00:00']);
        $this->antecipacao($solicitacao, Antecipacao::STATUS_GERADA, ['gerado_em' => '2026-09-07 10:00:00']);
        $this->antecipacao($solicitacao, Antecipacao::STATUS_IGNORADA, ['ignorado_em' => '2026-09-08 10:00:00']);
        // Fora do período.
        $this->antecipacao($solicitacao, Antecipacao::STATUS_GERADA, ['gerado_em' => '2026-08-08 10:00:00']);

        $kpis = $this->kpis();

        $this->assertSame(3, $kpis['antecipacoes_geradas']['valor']);
        $this->assertSame(1, $kpis['antecipacoes_ignoradas']['valor']);
        $this->assertSame(25.0, $kpis['taxa_dispensa_antecipacao']['valor']);
    }

    // ── Comparação com o período anterior ───────────────────────────────────

    public function test_o_periodo_anterior_e_calculado_com_o_mesmo_recorte(): void
    {
        $this->limparHistorico();

        // Setembro: 2 negadas. Agosto (2 a 31, o anterior de 30 dias): 1 negada.
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-05');
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-06');
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-08-20');

        $kpis = $this->kpis(comparar: true);

        $this->assertSame(100.0, $kpis['taxa_negacao']['valor']);
        $this->assertSame(100.0, $kpis['taxa_negacao']['anterior']);
    }

    public function test_sem_comparacao_o_anterior_e_nulo(): void
    {
        $this->limparHistorico();
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-05');

        $this->assertNull($this->kpis()['taxa_negacao']['anterior']);
    }

    // ── Isolamento ──────────────────────────────────────────────────────────

    public function test_nao_conta_guia_de_outra_clinica(): void
    {
        $this->limparHistorico();

        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-05');

        $vizinha = $this->clinicaVizinhaComGuiaNegada();

        $this->assertSame(1, $this->totalDeDecisoes($this->tenantId));
        $this->assertSame(1, $this->totalDeDecisoes($vizinha));
        $this->assertSame(2, $this->totalDeDecisoes(null), 'todas as clínicas somam as duas');
    }

    // ── Séries e tabelas ────────────────────────────────────────────────────

    public function test_series_e_tabelas_tem_as_chaves_que_a_tela_espera(): void
    {
        $relatorio = $this->relatorio();

        $this->assertSame(
            ['guias_por_status', 'tempo_de_decisao', 'funil_de_guias', 'solicitacoes_por_especialidade', 'sessoes_por_profissional', 'guias_por_convenio'],
            array_column($relatorio['series'], 'key'),
        );

        $this->assertSame(
            ['por_especialidade', 'por_profissional', 'por_convenio'],
            array_column($relatorio['tabelas'], 'key'),
        );
    }

    /** Série sem nenhum dado volta sem ponto, para a tela dizer "sem dados no período". */
    public function test_serie_sem_dado_volta_vazia(): void
    {
        $this->limparHistorico();

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'guias_por_status');

        $this->assertSame([], $serie['pontos']);
    }

    /** Já um balde sem ocorrência, dentro de uma série que tem dado, vale zero. */
    public function test_dia_sem_ocorrencia_vale_zero_dentro_da_serie(): void
    {
        $this->limparHistorico();
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-05');

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'guias_por_status');
        $pontos = collect($serie['pontos'])->keyBy('x');

        $this->assertCount(30, $serie['pontos'], 'um ponto por dia de setembro');
        $this->assertSame(1, $pontos['2026-09-05']['negadas']);
        $this->assertSame(0, $pontos['2026-09-06']['negadas']);
    }

    /** O funil traz os quatro degraus mesmo quando algum está vazio. */
    public function test_funil_traz_os_quatro_degraus(): void
    {
        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'funil_de_guias');

        $this->assertSame(
            ['Em análise', 'Autorizadas', 'Aprovadas', 'Negadas'],
            array_column($serie['pontos'], 'x'),
        );
    }

    public function test_a_tabela_por_convenio_soma_guias_e_decisoes(): void
    {
        $this->limparHistorico();

        $this->guiaDecidida(GuiaStatus::APPROVED, '2026-09-05', criadaEm: '2026-09-05 08:00:00');
        $this->guiaDecidida(GuiaStatus::DENIED, '2026-09-06', criadaEm: '2026-09-06 08:00:00');

        $tabela = collect($this->relatorio()['tabelas'])->firstWhere('key', 'por_convenio');
        $linha = collect($tabela['linhas'])->firstWhere('nome', 'Unimed');

        $this->assertSame(2, $linha['guias']);
        $this->assertSame(1, $linha['aprovadas']);
        $this->assertSame(1, $linha['negadas']);
        $this->assertSame(50.0, $linha['taxa_aprovacao']);
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /** @return array<string, array<string, mixed>> */
    private function kpis(bool $comparar = false): array
    {
        return collect($this->relatorio($comparar)['kpis'])->keyBy('key')->all();
    }

    /** @return array<string, mixed> */
    private function relatorio(bool $comparar = false, ?int $tenantId = null): array
    {
        return app(RelatorioOperacaoService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            // `func_num_args` não serve aqui: null significa "todas as clínicas".
            tenantId: $tenantId ?? $this->tenantId,
            comparar: $comparar,
        ));
    }

    private function totalDeDecisoes(?int $tenantId): int
    {
        $relatorio = app(RelatorioOperacaoService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $tenantId,
        ));

        $funil = collect($relatorio['series'])->firstWhere('key', 'funil_de_guias');

        return (int) collect($funil['pontos'])->firstWhere('x', 'Negadas')['total'];
    }

    private function guiaDecidida(string $status, string $dia, ?string $criadaEm = null): Guia
    {
        $guia = $this->guia($criadaEm ? ['created_at' => $criadaEm] : []);

        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, "{$dia} 08:00:00");
        $this->transicao($guia, $status, "{$dia} 12:00:00");

        return $guia;
    }

    private function guia(array $atributos = []): Guia
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
            'numero_guia' => 'REL-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => '2026-09-01',
            ...$atributos,
        ]);

        if (isset($atributos['created_at'])) {
            $guia->forceFill(['created_at' => $atributos['created_at']])->save();
        }

        // A criação da guia grava sozinha a primeira transição, com a hora de
        // agora. Cada teste escreve as suas, então esta sai do caminho.
        GuiaStatusHistorico::query()->where('guia_id', $guia->id)->delete();

        return $guia->refresh();
    }

    private function transicao(Guia $guia, string $para, string $quando): void
    {
        GuiaStatusHistorico::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'de' => null,
            'para' => $para,
            'ocorrido_em' => $quando,
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
        ]);
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

    private function solicitacao(): Solicitacao
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        return Solicitacao::query()->create([
            'tenant_id' => $this->tenantId,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'status' => 'under_review',
            'solicitado_em' => '2026-09-01',
        ]);
    }

    private function antecipacao(Solicitacao $solicitacao, string $status, array $atributos): void
    {
        Antecipacao::query()->create([
            'tenant_id' => $this->tenantId,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => $status,
            'data_alvo' => '2026-09-15',
            'itens_selecionados' => [],
            ...$atributos,
        ]);
    }

    private function clinicaVizinhaComGuiaNegada(): int
    {
        $vizinha = Tenant::factory()->create();

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $vizinha->id, 'nome' => 'Fisioterapia', 'ativo' => true,
        ]);
        $profissional = Profissional::query()->create([
            'tenant_id' => $vizinha->id, 'especialidade_id' => $especialidade->id,
            'nome' => 'Profissional Vizinho', 'ativo' => true,
        ]);
        $convenio = Convenio::query()->create([
            'tenant_id' => $vizinha->id, 'nome' => 'Unimed', 'connector_type' => 'manual', 'ativo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'tenant_id' => $vizinha->id, 'nome' => 'Paciente Vizinho',
            'carteirinha' => 'VIZ-1', 'convenio_id' => $convenio->id, 'ativo' => true,
        ]);

        $guia = Guia::query()->create([
            'tenant_id' => $vizinha->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'VIZ-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => '2026-09-01',
        ]);

        GuiaStatusHistorico::query()->where('guia_id', $guia->id)->delete();

        $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-05 08:00:00');
        $this->transicao($guia, GuiaStatus::DENIED, '2026-09-05 12:00:00');

        return (int) $vizinha->id;
    }

    /** O seeder já traz guias com histórico; os testes de número partem do zero. */
    private function limparHistorico(): void
    {
        GuiaStatusHistorico::query()->withoutGlobalScopes()->delete();
        Guia::query()->withoutGlobalScopes()->delete();
        Solicitacao::query()->withoutGlobalScopes()->delete();
    }

    private function limparSessoes(): void
    {
        Lancamento::query()->withoutGlobalScopes()->delete();
    }
}
