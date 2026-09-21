<?php

namespace App\Jobs;

use App\Models\AutomacaoExecucao;
use App\Models\SaudeComponente;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConfirmarGuiaIncertaUnimedService;
use App\Services\Automation\ConferirGuiaFinalizadaUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FinalizarGuiaUnimedService;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
use App\Services\SaudeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExecutarAutomacaoUnimedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $execucaoId)
    {
        $this->onQueue('automacoes');
    }

    public function handle(
        AutomacaoService $automacoes,
        UnimedWorkerClient $worker,
        GerarGuiaUnimedService $gerarGuiaUnimed,
        ConsultarStatusUnimedService $consultarStatusUnimed,
        UnimedCircuitBreakerService $circuitBreaker,
    ): void {
        $execucao = AutomacaoExecucao::query()->findOrFail($this->execucaoId);
        $capturarSenhaValidadeUnimed = app(CapturarSenhaValidadeUnimedService::class);
        $confirmarGuiaIncertaUnimed = app(ConfirmarGuiaIncertaUnimedService::class);
        $finalizarGuiaUnimed = app(FinalizarGuiaUnimedService::class);
        $conferirGuiaFinalizada = app(ConferirGuiaFinalizadaUnimedService::class);
        $lock = Cache::lock("automacao:unimed:tenant:{$execucao->tenant_id}:{$execucao->operacao}", 300);

        if (! $lock->get()) {
            $automacoes->falhar(
                $execucao,
                'TENANT_LOCK_UNAVAILABLE',
                'Já existe uma automação Unimed em execução para este tenant.',
            );

            return;
        }

        try {
            $execucao = $automacoes->iniciar($execucao);
            $payload = match ($execucao->operacao) {
                'gerar_guia' => $gerarGuiaUnimed->payloadParaWorker($execucao),
                'consultar_status', ConsultarStatusUnimedService::OPERATION => $consultarStatusUnimed->payloadParaWorker($execucao),
                CapturarSenhaValidadeUnimedService::OPERATION => $capturarSenhaValidadeUnimed->payloadParaWorker($execucao),
                ConfirmarGuiaIncertaUnimedService::OPERATION => $confirmarGuiaIncertaUnimed->payloadParaWorker($execucao),
                FinalizarGuiaUnimedService::OPERATION => $finalizarGuiaUnimed->payloadParaWorker($execucao),
                ConferirGuiaFinalizadaUnimedService::OPERATION => $conferirGuiaFinalizada->payloadParaWorker($execucao),
                default => $execucao->payload ?? [],
            };
            $resultado = $worker->executar($execucao, $payload);
            // A execucao inteira, e nao so o tenant: o disjuntor precisa saber de
            // qual convenio veio a falha para pausar so a credencial dele.
            $circuitBreaker->handleResult($execucao, $resultado);

            // Heartbeat aqui, e nao no fim do handle: o que prova que o worker
            // esta vivo e ele ter respondido, nao o resultado de negocio ter
            // sido "aprovado". Guia negada e resposta legitima de um worker
            // saudavel. Se `executar` lancar, caimos no catch e nenhum heartbeat
            // e registrado — que e exatamente o sinal desejado.
            //
            // O tenant vem explicito porque este job roda no queue:work, fora de
            // requisicao HTTP, entao nao ha TenantContext para resolver.
            app(SaudeService::class)->registrarHeartbeat(
                SaudeComponente::CHAVE_WORKER_UNIMED,
                tenantId: (int) $execucao->tenant_id,
            );

            if ($execucao->operacao === 'gerar_guia') {
                $gerarGuiaUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === 'consultar_status' || $execucao->operacao === ConsultarStatusUnimedService::OPERATION) {
                $consultarStatusUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === CapturarSenhaValidadeUnimedService::OPERATION) {
                $capturarSenhaValidadeUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === ConfirmarGuiaIncertaUnimedService::OPERATION) {
                $confirmarGuiaIncertaUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === FinalizarGuiaUnimedService::OPERATION) {
                $finalizarGuiaUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === ConferirGuiaFinalizadaUnimedService::OPERATION) {
                $conferirGuiaFinalizada->aplicarResultado($execucao, $resultado);
            } else {
                $automacoes->concluir($execucao, $resultado);
            }
        } catch (Throwable $exception) {
            $automacoes->falhar(
                $execucao,
                'WORKER_UNAVAILABLE',
                $exception->getMessage(),
            );
        } finally {
            $lock->release();
        }
    }
}
