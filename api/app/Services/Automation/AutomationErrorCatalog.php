<?php

namespace App\Services\Automation;

class AutomationErrorCatalog
{
    public const PORTAL_STRUCTURE_CHANGED = 'PORTAL_STRUCTURE_CHANGED';

    public function isStructural(?string $code): bool
    {
        return $code === self::PORTAL_STRUCTURE_CHANGED;
    }

    public function label(?string $code): string
    {
        return match ($code) {
            self::PORTAL_STRUCTURE_CHANGED => 'Estrutura do portal alterada',
            'WORKER_UNAVAILABLE' => 'Worker indisponível',
            'TENANT_LOCK_UNAVAILABLE' => 'Automação concorrente bloqueada',
            default => $code ?: 'Erro não classificado',
        };
    }
}
