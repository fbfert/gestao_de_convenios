<?php

namespace App\Services\Automation;

use App\Support\Auditoria;
use App\Models\UnimedRdaCredential;

class UnimedCircuitBreakerService
{
    public function __construct(private readonly AutomationErrorCatalog $catalog)
    {
    }

    public function handleResult(int $tenantId, array $result): void
    {
        $code = $result['error_code'] ?? $result['erro_codigo'] ?? null;

        if (! $this->catalog->isStructuralResult($result)) {
            return;
        }

        $credential = UnimedRdaCredential::query()->where('tenant_id', $tenantId)->first();

        if (! $credential) {
            return;
        }

        // O automatico e suspenso porque o evento explicito diz o motivo da
        // pausa, e `ativo: true -> false` sozinho nao diria.
        Auditoria::semRegistroAutomatico(fn () => $credential->forceFill([
            'ativo' => false,
            'automation_paused_at' => now(),
            'automation_paused_reason' => $code,
        ])->save());

        Auditoria::registrar(
            acao: 'unimed_rda.automation_paused',
            entidade: 'unimed_rda_credentials',
            entidadeId: (int) $credential->id,
            payload: [
                'reason' => $code,
                'label' => $this->catalog->label($code),
            ],
            tenantId: $tenantId,
            // Pausa vem do circuit breaker, nunca de uma pessoa: fica como
            // evento do sistema mesmo dentro de uma requisicao autenticada.
            doSistema: true,
        );
    }
}
