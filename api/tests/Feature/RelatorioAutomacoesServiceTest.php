<?php

namespace Tests\Feature;

use App\Models\AutomacaoExecucao;
use App\Models\ClinicaSyncExecucao;
use App\Models\SaudeComponente;
use App\Models\SaudeComponenteEvento;
use App\Models\Tenant;
use App\Services\Relatorios\RelatorioAutomacoesService;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Services\SaudeService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os números da aba de Automações.
 *
 * O ponto delicado aqui é o tempo fora do ar: ele precisa saber a diferença
 * entre "o componente esteve no ar o período todo" e "não tenho registro deste
 * período" — e nunca responder zero para a segunda.
 */
class RelatorioAutomacoesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const DE = '2026-09-01';

    private const ATE = '2026-09-30';

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $agora = CarbonImmutable::parse('2026-10-05 09:00:00', RelatorioPeriodo::FUSO);
        Carbon::setTestNow($agora);
        CarbonImmutable::setTestNow($agora);

        $this->tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($this->tenantId);

        AutomacaoExecucao::query()->withoutGlobalScopes()->delete();
        SaudeComponenteEvento::query()->withoutGlobalScopes()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ── Execuções ───────────────────────────────────────────────────────────

    public function test_taxa_de_sucesso_sobre_as_execucoes_encerradas(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 10:00:00', duracaoSegundos: 60);
        $this->execucao('gerar_guia', 'succeeded', '2026-09-06 10:00:00', duracaoSegundos: 120);
        $this->execucao('gerar_guia', 'succeeded', '2026-09-07 10:00:00', duracaoSegundos: 180);
        $this->execucao('gerar_guia', 'failed', '2026-09-08 10:00:00', duracaoSegundos: 240, erroCodigo: 'LOGIN_FALHOU');

        $kpis = $this->kpis();

        $this->assertSame(4, $kpis['execucoes']['valor']);
        $this->assertSame(75.0, $kpis['taxa_sucesso']['valor']);
    }

    /** Conta por `finished_at`: o relatório mede trabalho concluído. */
    public function test_execucao_encerrada_fora_do_periodo_nao_conta(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-10-01 10:00:00', duracaoSegundos: 60);

        $this->assertSame(0, $this->kpis()['execucoes']['valor']);
    }

    /** Execução em curso não entra em período nenhum até terminar. */
    public function test_execucao_em_curso_nao_conta(): void
    {
        AutomacaoExecucao::query()->create([
            'tenant_id' => $this->tenantId,
            'operacao' => 'gerar_guia',
            'status' => 'running',
            'idempotency_key' => 'em-curso-'.uniqid(),
            'queued_at' => '2026-09-10 10:00:00',
            'started_at' => '2026-09-10 10:00:05',
        ]);

        $this->assertSame(0, $this->kpis()['execucoes']['valor']);
    }

    public function test_duracao_media_e_percentil_95(): void
    {
        // 10 execuções de 10s a 100s: média 55s; o p95 é a nona (90s).
        foreach (range(1, 10) as $i) {
            $this->execucao('gerar_guia', 'succeeded', '2026-09-1'.($i % 10).' 10:00:00', duracaoSegundos: $i * 10);
        }

        $kpis = $this->kpis();

        // Em segundos crus: a unidade é escolha da tela, e "0,1 h" não deixa
        // ninguém comparar duas execuções.
        $this->assertSame(55.0, $kpis['duracao_media']['valor']);
        $this->assertSame(100.0, $kpis['duracao_p95']['valor']);
    }

    public function test_tempo_medio_em_fila(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 10:00:00', duracaoSegundos: 60, filaSegundos: 3600);
        $this->execucao('gerar_guia', 'succeeded', '2026-09-06 10:00:00', duracaoSegundos: 60, filaSegundos: 10800);

        $this->assertSame(7200.0, $this->kpis()['tempo_medio_fila']['valor'], 'média de 1h e 3h, em segundos');
    }

    public function test_reprocessamento_e_contado_pelo_pai(): void
    {
        $pai = $this->execucao('gerar_guia', 'failed', '2026-09-05 10:00:00', duracaoSegundos: 60);
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 11:00:00', duracaoSegundos: 60, parentId: $pai->id);

        $this->assertSame(1, $this->kpis()['reprocessamentos']['valor']);
    }

    public function test_pareto_agrupa_pelo_codigo_de_erro(): void
    {
        $this->execucao('gerar_guia', 'failed', '2026-09-05 10:00:00', duracaoSegundos: 10, erroCodigo: 'LOGIN_FALHOU');
        $this->execucao('gerar_guia', 'failed', '2026-09-06 10:00:00', duracaoSegundos: 10, erroCodigo: 'LOGIN_FALHOU');
        $this->execucao('gerar_guia', 'uncertain', '2026-09-07 10:00:00', duracaoSegundos: 10, erroCodigo: 'SEM_RESPOSTA');

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'pareto_de_erros');

        $this->assertSame(
            [['x' => 'LOGIN_FALHOU', 'total' => 2], ['x' => 'SEM_RESPOSTA', 'total' => 1]],
            $serie['pontos'],
        );
    }

    public function test_sincronizacoes_com_o_clinica(): void
    {
        $this->sincronizacao('ok', '2026-09-05 10:00:00');
        $this->sincronizacao('ok', '2026-09-06 10:00:00');
        $this->sincronizacao('error', '2026-09-07 10:00:00');

        $kpis = $this->kpis();

        $this->assertSame(3, $kpis['sincronizacoes_clinica']['valor']);
        $this->assertSame(1, $kpis['sincronizacoes_com_erro']['valor']);
    }

    // ── Tempo fora do ar ────────────────────────────────────────────────────

    public function test_soma_as_horas_entre_a_queda_e_a_volta(): void
    {
        $componente = $this->componente();

        $this->evento($componente, SaudeComponente::ESTADO_SAUDAVEL, '2026-09-01 00:00:00');
        $this->evento($componente, SaudeComponente::ESTADO_FORA, '2026-09-10 08:00:00');
        $this->evento($componente, SaudeComponente::ESTADO_SAUDAVEL, '2026-09-10 12:00:00');

        $this->assertSame(4.0, $this->kpis()['horas_fora_do_ar']['valor']);
    }

    /** Queda que não terminou conta até o fim do período, não até agora. */
    public function test_queda_sem_volta_conta_ate_o_fim_do_periodo(): void
    {
        $componente = $this->componente();

        $this->evento($componente, SaudeComponente::ESTADO_SAUDAVEL, '2026-09-01 00:00:00');
        $this->evento($componente, SaudeComponente::ESTADO_FORA, '2026-09-30 22:00:00');

        // Das 22h do dia 30 até 23:59:59 do dia 30: 2 horas (arredondadas).
        $this->assertSame(2.0, $this->kpis()['horas_fora_do_ar']['valor']);
    }

    /** Queda que começou ANTES do período conta só a parte que cai dentro dele. */
    public function test_queda_anterior_ao_periodo_conta_so_a_parte_de_dentro(): void
    {
        $componente = $this->componente();

        $this->evento($componente, SaudeComponente::ESTADO_FORA, '2026-08-30 08:00:00');
        $this->evento($componente, SaudeComponente::ESTADO_SAUDAVEL, '2026-09-01 06:00:00');

        $this->assertSame(6.0, $this->kpis()['horas_fora_do_ar']['valor']);
    }

    /**
     * Sem registro nenhum, o indicador é AUSENTE.
     *
     * Zero afirmaria "esteve no ar o período todo", e o que o sistema sabe é
     * que não tem registro — a tabela de eventos só existe do deploy em diante.
     */
    public function test_sem_registro_de_estado_o_indicador_e_ausente(): void
    {
        $this->componente();

        $this->assertNull($this->kpis()['horas_fora_do_ar']['valor']);
    }

    public function test_componente_sempre_no_ar_soma_zero(): void
    {
        $componente = $this->componente();

        $this->evento($componente, SaudeComponente::ESTADO_SAUDAVEL, '2026-09-01 00:00:00');

        $this->assertSame(0.0, $this->kpis()['horas_fora_do_ar']['valor']);
    }

    // ── Gravação do histórico ───────────────────────────────────────────────

    /** Sinal de vida sem mudança de estado não gera evento. */
    public function test_heartbeat_repetido_nao_gera_evento(): void
    {
        $componente = $this->componente();

        app(SaudeService::class)->registrarHeartbeat($componente->chave, tenantId: $this->tenantId);
        app(SaudeService::class)->registrarHeartbeat($componente->chave, tenantId: $this->tenantId);
        app(SaudeService::class)->registrarHeartbeat($componente->chave, tenantId: $this->tenantId);

        $this->assertSame(
            1,
            SaudeComponenteEvento::query()->withoutGlobalScopes()->where('saude_componente_id', $componente->id)->count(),
            'só a linha de partida do histórico',
        );
    }

    /** A queda é vista pela varredura, e a volta pelo próprio heartbeat. */
    public function test_queda_e_volta_geram_um_evento_cada(): void
    {
        $componente = $this->componente();
        $saude = app(SaudeService::class);

        $saude->registrarHeartbeat($componente->chave, tenantId: $this->tenantId);

        // Tempo passa muito além do intervalo esperado: o componente cai.
        Carbon::setTestNow(now()->addHours(3));
        CarbonImmutable::setTestNow(now()->addHours(3));

        $saude->sincronizarEstados();
        $saude->sincronizarEstados();

        $saude->registrarHeartbeat($componente->chave, tenantId: $this->tenantId);

        $estados = SaudeComponenteEvento::query()
            ->withoutGlobalScopes()
            ->where('saude_componente_id', $componente->id)
            ->orderBy('id')
            ->pluck('estado')
            ->all();

        $this->assertSame(
            [SaudeComponente::ESTADO_SAUDAVEL, SaudeComponente::ESTADO_FORA, SaudeComponente::ESTADO_SAUDAVEL],
            $estados,
            'a segunda varredura não repete o mesmo estado',
        );
    }

    // ── Filtros e contrato ──────────────────────────────────────────────────

    /**
     * A execução não carrega convênio, especialidade nem profissional, então o
     * filtro é ignorado — e a resposta diz isso, em vez de deixar o usuário
     * supor que o número está recortado.
     */
    public function test_filtros_de_convenio_e_profissional_sao_ignorados(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 10:00:00', duracaoSegundos: 60);

        $relatorio = app(RelatorioAutomacoesService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $this->tenantId,
            convenioId: 1,
        ));

        $this->assertSame([], $relatorio['filtros_aplicados']);
        $this->assertSame(1, collect($relatorio['kpis'])->firstWhere('key', 'execucoes')['valor']);
    }

    public function test_nao_conta_execucao_de_outra_clinica(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 10:00:00', duracaoSegundos: 60);

        $vizinha = Tenant::factory()->create();
        AutomacaoExecucao::query()->create([
            'tenant_id' => $vizinha->id,
            'operacao' => 'gerar_guia',
            'status' => 'succeeded',
            'idempotency_key' => 'viz-'.uniqid(),
            'queued_at' => '2026-09-05 10:00:00',
            'started_at' => '2026-09-05 10:00:01',
            'finished_at' => '2026-09-05 10:01:00',
        ]);

        $this->assertSame(1, $this->kpis()['execucoes']['valor']);
    }

    public function test_series_e_tabelas_tem_as_chaves_que_a_tela_espera(): void
    {
        $relatorio = $this->relatorio();

        $this->assertSame(
            ['execucoes_por_dia', 'execucoes_por_operacao', 'pareto_de_erros', 'duracao_das_execucoes', 'sincronizacao_clinica'],
            array_column($relatorio['series'], 'key'),
        );

        $this->assertSame(
            ['por_operacao', 'por_erro', 'componentes_fora_do_ar'],
            array_column($relatorio['tabelas'], 'key'),
        );
    }

    /** A linha da tabela leva o filtro que abre /automacoes já recortado. */
    public function test_a_tabela_por_operacao_carrega_o_filtro_da_listagem(): void
    {
        $this->execucao('gerar_guia', 'succeeded', '2026-09-05 10:00:00', duracaoSegundos: 60);

        $tabela = collect($this->relatorio()['tabelas'])->firstWhere('key', 'por_operacao');

        $this->assertSame('/automacoes?operacao=gerar_guia', $tabela['linhas'][0]['href']);
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /** @return array<string, array<string, mixed>> */
    private function kpis(): array
    {
        return collect($this->relatorio()['kpis'])->keyBy('key')->all();
    }

    /** @return array<string, mixed> */
    private function relatorio(): array
    {
        return app(RelatorioAutomacoesService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $this->tenantId,
        ));
    }

    private function execucao(
        string $operacao,
        string $status,
        string $terminouEm,
        int $duracaoSegundos,
        ?string $erroCodigo = null,
        ?int $parentId = null,
        int $filaSegundos = 0,
    ): AutomacaoExecucao {
        $fim = CarbonImmutable::parse($terminouEm, RelatorioPeriodo::FUSO);
        $inicio = $fim->subSeconds($duracaoSegundos);

        return AutomacaoExecucao::query()->create([
            'tenant_id' => $this->tenantId,
            'operacao' => $operacao,
            'status' => $status,
            'idempotency_key' => 'exec-'.uniqid('', true),
            'erro_codigo' => $erroCodigo,
            'queued_at' => $inicio->subSeconds($filaSegundos),
            'started_at' => $inicio,
            'finished_at' => $fim,
            'parent_id' => $parentId,
        ]);
    }

    private function sincronizacao(string $status, string $iniciadoEm): void
    {
        ClinicaSyncExecucao::query()->create([
            'tenant_id' => $this->tenantId,
            'origem' => 'agendado',
            'status' => $status,
            'iniciado_em' => $iniciadoEm,
            'finalizado_em' => $iniciadoEm,
        ]);
    }

    private function componente(): SaudeComponente
    {
        SaudeComponente::query()->withoutGlobalScopes()->delete();

        return SaudeComponente::query()->create([
            'tenant_id' => $this->tenantId,
            'chave' => 'relatorio.teste',
            'nome' => 'Componente de teste',
            'tipo' => 'interno',
            'intervalo_esperado_segundos' => 120,
            'ativo' => true,
        ]);
    }

    private function evento(SaudeComponente $componente, string $estado, string $quando): void
    {
        SaudeComponenteEvento::query()->create([
            'tenant_id' => $componente->tenant_id,
            'saude_componente_id' => $componente->id,
            'estado' => $estado,
            'ocorrido_em' => $quando,
        ]);
    }
}
