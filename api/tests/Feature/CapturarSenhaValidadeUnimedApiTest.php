<?php

namespace Tests\Feature;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o bug ao vivo de 22/09/2026 (guia 50144652656): a tela de execucao do
 * SP/SADT traz senha/validade E sessoes solicitadas/autorizadas juntas, mas
 * capturarAutorizacaoGuia (worker) e aplicarResultado (aqui) so persistiam
 * senha/validade — sessoes ficavam 0 pra sempre numa guia ja "approved" (fora
 * da elegibilidade de ConsultarStatusUnimedService) e ja com senha/validade
 * preenchidas (fora da elegibilidade desta propria operacao).
 */
class CapturarSenhaValidadeUnimedApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_captura_persiste_sessoes_quando_worker_traz_junto_com_senha_e_validade(): void
    {
        $guia = $this->criarGuiaAprovadaSemSenha();

        $execucao = app(CapturarSenhaValidadeUnimedService::class)->enviar($guia, dispatch: false);

        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'senha' => '9248082',
            'validade_senha' => '2026-10-24',
            'sessoes_solicitadas' => 10,
            'sessoes_autorizadas' => 6,
        ]));

        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertSame('succeeded', $execucao->refresh()->status);
        $this->assertSame('9248082', $guia->senha);
        $this->assertSame('2026-10-24', $guia->validade_senha->format('Y-m-d'));
        $this->assertSame(10, $guia->sessoes_solicitadas);
        $this->assertSame(6, $guia->sessoes_autorizadas);
    }

    public function test_captura_sem_sessoes_no_resultado_nao_mexe_nas_sessoes_existentes(): void
    {
        $guia = $this->criarGuiaAprovadaSemSenha();
        $guia->forceFill(['sessoes_solicitadas' => 10, 'sessoes_autorizadas' => 10])->save();

        $execucao = app(CapturarSenhaValidadeUnimedService::class)->enviar($guia, dispatch: false);

        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'senha' => '9248082',
            'validade_senha' => '2026-10-24',
        ]));

        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertSame('9248082', $guia->senha);
        $this->assertSame(10, $guia->sessoes_solicitadas);
        $this->assertSame(10, $guia->sessoes_autorizadas);
    }

    private function executarJob(\App\Models\AutomacaoExecucao $execucao): void
    {
        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(UnimedCircuitBreakerService::class),
        );
    }

    private function criarGuiaAprovadaSemSenha(): Guia
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->update(['connector_type' => 'scraping', 'connector_driver' => 'unimed_rda']);
        $tenantId = (int) $convenio->tenant_id;

        ConvenioCredencial::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
        ], [
            'driver' => 'unimed_rda',
            'credenciais' => [
                'login' => 'operador-unimed',
                'password' => 'senha-unimed',
                'base_url' => 'https://portal.unimed.test',
            ],
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->where('tenant_id', $tenantId)->where('convenio_id', $convenio->id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenantId)->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $tenantId)->where('especialidade_id', $especialidade->id)->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'status' => 'ready_for_automation',
            'solicitado_em' => today(),
            'observacoes' => null,
        ]);

        $item = $solicitacao->itens()->create([
            'tenant_id' => $tenantId,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'guia_generated',
        ]);

        return Guia::query()->create([
            'tenant_id' => $tenantId,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'UNI-CAPTURA-1',
            'tipo_terapia' => 'especializada',
            'status' => 'approved',
            'unimed_status' => 'Autorizado',
            'sessoes_solicitadas' => 0,
            'sessoes_autorizadas' => 0,
            'data_solicitacao' => today(),
            'aprovada_em' => now(),
        ]);
    }
}
