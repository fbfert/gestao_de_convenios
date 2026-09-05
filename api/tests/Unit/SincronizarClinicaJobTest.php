<?php

namespace Tests\Unit;

use App\Jobs\SincronizarClinicaJob;
use App\Models\ClinicaConexaoConfig;
use App\Models\ClinicaSyncExecucao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use App\Services\ClinicaSync\ClinicaSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SincronizarClinicaJobTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function config(?Carbon $ultimaExecucaoEm): ClinicaConexaoConfig
    {
        return ClinicaConexaoConfig::query()->create([
            'tenant_id' => $this->tenantId(),
            'base_url' => 'https://clinica.test',
            'token' => 'token-teste',
            'ativo' => true,
            'ultima_execucao_em' => $ultimaExecucaoEm,
        ]);
    }

    private function automacao(array $overrides = []): void
    {
        ConfiguracaoGlobal::query()->updateOrCreate(
            ['tenant_id' => $this->tenantId()],
            array_merge([
                'automacao_sincronizacao_clinica_ativo' => true,
                'automacao_sincronizacao_clinica_diurno_horario_inicio' => '08:00:00',
                'automacao_sincronizacao_clinica_diurno_horario_fim' => '18:00:00',
                'automacao_sincronizacao_clinica_diurno_intervalo_minutos' => 10,
                'automacao_sincronizacao_clinica_noturno_horario_inicio' => '18:00:00',
                'automacao_sincronizacao_clinica_noturno_horario_fim' => '22:00:00',
                'automacao_sincronizacao_clinica_noturno_intervalo_minutos' => 30,
                'automacao_sincronizacao_clinica_madrugada_horario_inicio' => '22:00:00',
                'automacao_sincronizacao_clinica_madrugada_horario_fim' => '07:59:00',
                'automacao_sincronizacao_clinica_madrugada_intervalo_minutos' => 60,
            ], $overrides),
        );
    }

    private function mockServico(bool $esperaExecucao): void
    {
        $mock = Mockery::mock(ClinicaSyncService::class);

        if ($esperaExecucao) {
            $mock->shouldReceive('executar')->once()->andReturn(new ClinicaSyncExecucao());
        } else {
            $mock->shouldReceive('executar')->never();
        }

        $this->app->instance(ClinicaSyncService::class, $mock);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_janela_diurna_respeita_intervalo_de_10_minutos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 10:00:00'));
        $this->automacao();
        $this->config(Carbon::parse('2026-09-05 09:52:00')); // 8 min atrás
        $this->mockServico(esperaExecucao: false);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }

    public function test_janela_diurna_executa_apos_10_minutos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 10:00:00'));
        $this->automacao();
        $this->config(Carbon::parse('2026-09-05 09:48:00')); // 12 min atrás
        $this->mockServico(esperaExecucao: true);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }

    public function test_janela_noturna_usa_intervalo_de_30_minutos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 19:00:00'));
        $this->automacao();
        $this->config(Carbon::parse('2026-09-05 18:35:00')); // 25 min atrás — ainda não passou 30
        $this->mockServico(esperaExecucao: false);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }

    public function test_janela_madrugada_cruza_meia_noite_e_usa_60_minutos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 01:00:00')); // depois da meia-noite
        $this->automacao();
        $this->config(Carbon::parse('2026-09-06 00:30:00')); // 30 min atrás — não passou 60
        $this->mockServico(esperaExecucao: false);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }

    public function test_janela_madrugada_executa_apos_60_minutos_cruzando_meia_noite(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 00:30:00'));
        $this->automacao();
        $this->config(Carbon::parse('2026-09-05 23:00:00')); // 90 min atrás, do dia anterior
        $this->mockServico(esperaExecucao: true);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }

    public function test_origem_manual_sempre_executa_mesmo_com_automacao_desligada(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 10:00:00'));
        $this->automacao(['automacao_sincronizacao_clinica_ativo' => false]);
        $this->config(Carbon::parse('2026-09-05 09:59:00'));
        $this->mockServico(esperaExecucao: true);

        (new SincronizarClinicaJob('manual'))->handle(app(ClinicaSyncService::class));
    }

    public function test_automacao_desligada_nao_executa_no_tick_agendado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 10:00:00'));
        $this->automacao(['automacao_sincronizacao_clinica_ativo' => false]);
        $this->config(null);
        $this->mockServico(esperaExecucao: false);

        (new SincronizarClinicaJob())->handle(app(ClinicaSyncService::class));
    }
}
