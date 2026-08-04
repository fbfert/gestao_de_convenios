<?php

namespace App\Services\Automation;

use App\Models\AuditLog;
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

        $credential->forceFill([
            'ativo' => false,
            'automation_paused_at' => now(),
            'automation_paused_reason' => $code,
        ])->save();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'acao' => 'unimed_rda.automation_paused',
            'entidade' => 'unimed_rda_credentials',
            'entidade_id' => $credential->id,
            'payload' => [
                'reason' => $code,
                'label' => $this->catalog->label($code),
            ],
        ]);
    }
}
