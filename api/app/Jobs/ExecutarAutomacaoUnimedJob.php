<?php

namespace App\Jobs;

use App\Models\AutomacaoExecucao;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
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
        $lock = Cache::lock("automacao:unimed:tenant:{$execucao->tenant_id}", 300);

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
                'consultar_status' => $consultarStatusUnimed->payloadParaWorker($execucao),
                default => $execucao->payload ?? [],
            };
            $resultado = $worker->executar($execucao, $payload);
            $circuitBreaker->handleResult((int) $execucao->tenant_id, $resultado);

            if ($execucao->operacao === 'gerar_guia') {
                $gerarGuiaUnimed->aplicarResultado($execucao, $resultado);
            } elseif ($execucao->operacao === 'consultar_status') {
                $consultarStatusUnimed->aplicarResultado($execucao, $resultado);
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
