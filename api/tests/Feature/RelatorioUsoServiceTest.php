<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Services\Relatorios\RelatorioUsoService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Os números da aba de Uso, todos saindo da trilha de auditoria.
 */
class RelatorioUsoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const DE = '2026-09-01';

    private const ATE = '2026-09-30';

    private int $tenantId;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $agora = CarbonImmutable::parse('2026-10-05 09:00:00', RelatorioPeriodo::FUSO);
        Carbon::setTestNow($agora);
        CarbonImmutable::setTestNow($agora);

        $this->tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($this->tenantId);

        $this->admin = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        AuditLog::query()->withoutGlobalScopes()->delete();
        Alerta::query()->withoutGlobalScopes()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_usuarios_ativos_e_acessos(): void
    {
        $funcionario = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();

        $this->acao($this->admin, 'acesso.login', 'users', '2026-09-05 08:00:00');
        $this->acao($this->admin, 'updated', 'guias', '2026-09-05 09:00:00');
        $this->acao($funcionario, 'acesso.login', 'users', '2026-09-06 08:00:00');
        // Fora do período.
        $this->acao($funcionario, 'acesso.login', 'users', '2026-08-06 08:00:00');

        $kpis = $this->kpis();

        $this->assertSame(2, $kpis['usuarios_ativos']['valor']);
        $this->assertSame(2, $kpis['acessos']['valor']);
        $this->assertSame(3, $kpis['acoes']['valor']);
    }

    /** A média é sobre os dias do período, e não sobre os dias com movimento. */
    public function test_acoes_por_dia_divide_pelo_periodo_inteiro(): void
    {
        foreach (range(1, 60) as $i) {
            $this->acao($this->admin, 'updated', 'guias', '2026-09-05 09:00:00');
        }

        $this->assertSame(2, $this->kpis()['acoes_por_dia']['valor'], '60 ações em 30 dias');
    }

    public function test_serie_por_hora_do_dia_traz_as_24_horas(): void
    {
        $this->acao($this->admin, 'updated', 'guias', '2026-09-05 09:30:00');
        $this->acao($this->admin, 'updated', 'guias', '2026-09-06 09:45:00');
        $this->acao($this->admin, 'updated', 'guias', '2026-09-06 14:00:00');

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'acoes_por_hora');
        $porHora = collect($serie['pontos'])->keyBy('x');

        $this->assertCount(24, $serie['pontos']);
        $this->assertSame(2, $porHora['09h']['total']);
        $this->assertSame(1, $porHora['14h']['total']);
        $this->assertSame(0, $porHora['03h']['total'], 'hora sem movimento vale zero, e não some');
    }

    /** Sem nenhuma ação, a série volta vazia para a tela dizer "sem dados". */
    public function test_serie_por_hora_sem_acao_volta_vazia(): void
    {
        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'acoes_por_hora');

        $this->assertSame([], $serie['pontos']);
    }

    public function test_tabela_por_usuario_traz_acoes_acessos_e_ultima_acao(): void
    {
        $this->acao($this->admin, 'acesso.login', 'users', '2026-09-05 08:00:00');
        $this->acao($this->admin, 'updated', 'guias', '2026-09-20 17:30:00');

        $tabela = collect($this->relatorio()['tabelas'])->firstWhere('key', 'por_usuario');
        $linha = collect($tabela['linhas'])->firstWhere('nome', $this->admin->name);

        $this->assertSame(2, $linha['acoes']);
        $this->assertSame(1, $linha['acessos']);
        $this->assertStringStartsWith('2026-09-20', (string) $linha['ultimo_acesso']);
    }

    public function test_importacoes_confirmadas_e_taxa_de_linhas_invalidas(): void
    {
        $this->lote('paciente_import_lotes', '2026-09-10 10:00:00', linhas: 100, invalidas: 10);
        $this->lote('guia_import_lotes', '2026-09-11 10:00:00', linhas: 100, invalidas: 30);
        // Pré-visualização abandonada: não é importação.
        $this->lote('guia_import_lotes', null, linhas: 500, invalidas: 500);
        // Fora do período.
        $this->lote('guia_import_lotes', '2026-08-11 10:00:00', linhas: 100, invalidas: 0);

        $kpis = $this->kpis();

        $this->assertSame(2, $kpis['importacoes']['valor']);
        $this->assertSame(20.0, $kpis['taxa_linhas_invalidas']['valor']);

        $serie = collect($this->relatorio()['series'])->firstWhere('key', 'importacoes_por_tipo');

        $this->assertSame(
            [['x' => 'Pacientes', 'total' => 1], ['x' => 'Guias', 'total' => 1]],
            $serie['pontos'],
        );
    }

    public function test_alertas_gerados_reconhecidos_e_tempo_ate_reconhecer(): void
    {
        $this->alerta('2026-09-05 08:00:00', reconhecidoEm: '2026-09-05 10:00:00');
        $this->alerta('2026-09-06 08:00:00', reconhecidoEm: '2026-09-06 12:00:00');
        $this->alerta('2026-09-07 08:00:00');

        $kpis = $this->kpis();

        $this->assertSame(3, $kpis['alertas_gerados']['valor']);
        $this->assertSame(2, $kpis['alertas_reconhecidos']['valor']);
        $this->assertSame(3.0, $kpis['tempo_medio_reconhecimento']['valor'], 'média de 2h e 4h');
    }

    public function test_nao_conta_trilha_de_outra_clinica(): void
    {
        $this->acao($this->admin, 'updated', 'guias', '2026-09-05 09:00:00');

        $vizinha = Tenant::factory()->create();
        AuditLog::query()->create([
            'tenant_id' => $vizinha->id,
            'user_id' => null,
            'acao' => 'updated',
            'entidade' => 'guias',
            'entidade_id' => 1,
            'created_at' => '2026-09-05 09:00:00',
        ]);

        $this->assertSame(1, $this->kpis()['acoes']['valor']);
    }

    public function test_series_e_tabelas_tem_as_chaves_que_a_tela_espera(): void
    {
        $relatorio = $this->relatorio();

        $this->assertSame(
            ['acoes_por_dia', 'acoes_por_hora', 'acoes_por_entidade', 'acoes_por_usuario', 'importacoes_por_tipo'],
            array_column($relatorio['series'], 'key'),
        );

        $this->assertSame(['por_usuario', 'por_entidade'], array_column($relatorio['tabelas'], 'key'));
    }

    /** A trilha não carrega convênio nem especialidade: o filtro é ignorado e dito. */
    public function test_filtros_de_convenio_e_especialidade_sao_ignorados(): void
    {
        $relatorio = app(RelatorioUsoService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $this->tenantId,
            convenioId: 1,
            especialidadeId: 1,
        ));

        $this->assertSame([], $relatorio['filtros_aplicados']);
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
        return app(RelatorioUsoService::class)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $this->tenantId,
        ));
    }

    private function acao(User $usuario, string $acao, string $entidade, string $quando): void
    {
        AuditLog::query()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $usuario->id,
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => 1,
            'created_at' => $quando,
        ]);
    }

    private function alerta(string $abertoEm, ?string $reconhecidoEm = null): void
    {
        Alerta::query()->create([
            'tenant_id' => $this->tenantId,
            'chave' => 'teste',
            'nivel' => Alerta::NIVEL_AMARELO,
            'titulo' => 'Alerta de teste',
            'entidade' => 'guias',
            'entidade_id' => 1,
            'aberto_em' => $abertoEm,
            'reconhecido_em' => $reconhecidoEm,
            'reconhecido_por' => $reconhecidoEm ? $this->admin->id : null,
        ]);
    }

    private function lote(string $tabela, ?string $confirmadoEm, int $linhas, int $invalidas): void
    {
        DB::table($tabela)->insert([
            'tenant_id' => $this->tenantId,
            'arquivo_nome_original' => 'planilha.xlsx',
            'status' => $confirmadoEm ? 'confirmado' : 'previsualizado',
            'confirmado_em' => $confirmadoEm,
            'total_linhas' => $linhas,
            'total_validas' => $linhas - $invalidas,
            'total_invalidas' => $invalidas,
            'created_at' => $confirmadoEm ?? '2026-09-01 10:00:00',
            'updated_at' => $confirmadoEm ?? '2026-09-01 10:00:00',
        ]);
    }
}
