<?php

namespace Tests\Feature;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoEvento;
use App\Models\AutomacaoExecucao;
use App\Models\SolicitacaoItem;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class AutomacaoUnimedInfraTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_enfileira_execucao_com_idempotencia_evento_e_payload_redigido(): void
    {
        $item = SolicitacaoItem::query()->firstOrFail();
        $service = app(AutomacaoService::class);

        $execucao = $service->enfileirar($item->tenant_id, 'operacao_mock', $item, payload: [
            'login' => 'operador',
            'password' => 'senha-secreta',
            'nested' => ['token' => 'token-secreto'],
        ]);

        $repetida = $service->enfileirar($item->tenant_id, 'operacao_mock', $item, payload: [
            'password' => 'outra-senha',
        ]);

        $this->assertTrue($execucao->is($repetida));
        $this->assertSame('queued', $execucao->status);
        $this->assertSame('[REDACTED]', $execucao->payload['password']);
        $this->assertSame('[REDACTED]', $execucao->payload['nested']['token']);
        $this->assertSame(1, AutomacaoExecucao::query()->count());
        $this->assertDatabaseHas('automacao_eventos', [
            'automacao_execucao_id' => $execucao->id,
            'tipo' => 'queued',
            'status' => 'queued',
        ]);
    }

    public function test_bloqueia_execucao_concorrente_para_mesmo_tenant_e_operacao(): void
    {
        $item = SolicitacaoItem::query()->firstOrFail();
        $outroItem = $this->novoItemDoMesmoTenant($item);
        $service = app(AutomacaoService::class);

        $ativa = $service->enfileirar($item->tenant_id, 'gerar_guia', $item);

        try {
            $service->enfileirar($item->tenant_id, 'gerar_guia', $outroItem);
            $this->fail('Concorrencia deveria ter sido bloqueada.');
        } catch (AutomationConcurrencyException $exception) {
            $this->assertSame($ativa->id, $exception->execucaoId);
        }
    }

    public function test_job_chama_worker_fake_e_registra_resultado(): void
    {
        $item = SolicitacaoItem::query()->firstOrFail();
        $service = app(AutomacaoService::class);
        $execucao = $service->enfileirar($item->tenant_id, 'operacao_mock', $item, payload: [
            'password' => 'senha-secreta',
        ]);
        $worker = new FakeUnimedWorkerClient(['status' => 'succeeded', 'numero_guia' => '12345']);
        $this->app->instance(UnimedWorkerClient::class, $worker);

        app(ExecutarAutomacaoUnimedJob::class, ['execucaoId' => $execucao->id])
            ->handle(
                app(AutomacaoService::class),
                app(UnimedWorkerClient::class),
                app(\App\Services\Automation\GerarGuiaUnimedService::class),
                app(\App\Services\Automation\ConsultarStatusUnimedService::class),
                app(\App\Services\Automation\UnimedCircuitBreakerService::class),
            );

        $execucao->refresh();

        $this->assertSame('succeeded', $execucao->status);
        $this->assertSame('12345', $execucao->resultado['numero_guia']);
        $this->assertSame('[REDACTED]', $worker->calls[0]['payload']['password']);
        $this->assertSame(3, AutomacaoEvento::query()->where('automacao_execucao_id', $execucao->id)->count());
    }

    public function test_job_falha_quando_lock_do_tenant_esta_ocupado(): void
    {
        $item = SolicitacaoItem::query()->firstOrFail();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'operacao_mock', $item);
        $lock = Cache::lock("automacao:unimed:tenant:{$item->tenant_id}", 300);
        $lock->get();

        try {
            app(ExecutarAutomacaoUnimedJob::class, ['execucaoId' => $execucao->id])
                ->handle(
                    app(AutomacaoService::class),
                    new FakeUnimedWorkerClient(),
                    app(\App\Services\Automation\GerarGuiaUnimedService::class),
                    app(\App\Services\Automation\ConsultarStatusUnimedService::class),
                    app(\App\Services\Automation\UnimedCircuitBreakerService::class),
                );
        } finally {
            $lock->release();
        }

        $execucao->refresh();

        $this->assertSame('failed', $execucao->status);
        $this->assertSame('TENANT_LOCK_UNAVAILABLE', $execucao->erro_codigo);
    }

    public function test_job_registra_falha_operacional_quando_worker_nao_responde(): void
    {
        $item = SolicitacaoItem::query()->firstOrFail();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'operacao_mock', $item);

        app(ExecutarAutomacaoUnimedJob::class, ['execucaoId' => $execucao->id])
            ->handle(app(AutomacaoService::class), new class implements UnimedWorkerClient {
                public function executar(AutomacaoExecucao $execucao, array $payload): array
                {
                    throw new RuntimeException('worker fora do ar');
                }

                public function health(): array
                {
                    return ['status' => 'unavailable'];
                }
            }, app(\App\Services\Automation\GerarGuiaUnimedService::class), app(\App\Services\Automation\ConsultarStatusUnimedService::class), app(\App\Services\Automation\UnimedCircuitBreakerService::class));

        $execucao->refresh();

        $this->assertSame('failed', $execucao->status);
        $this->assertSame('WORKER_UNAVAILABLE', $execucao->erro_codigo);
    }

    private function novoItemDoMesmoTenant(SolicitacaoItem $item): SolicitacaoItem
    {
        return SolicitacaoItem::query()->create([
            'tenant_id' => $item->tenant_id,
            'solicitacao_id' => $item->solicitacao_id,
            'especialidade_id' => $item->especialidade_id,
            'profissional_id' => $item->profissional_id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);
    }
}
