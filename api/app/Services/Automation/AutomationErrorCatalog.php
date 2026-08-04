<?php

namespace App\Services\Automation;

class AutomationErrorCatalog
{
    public const PORTAL_STRUCTURE_CHANGED = 'PORTAL_STRUCTURE_CHANGED';
    public const LOGIN_ERROR = 'LOGIN_ERROR';
    public const PORTAL_UNAVAILABLE = 'PORTAL_UNAVAILABLE';
    public const SESSION_LOST_UNRECOVERABLE = 'SESSION_LOST_UNRECOVERABLE';
    public const WORKER_INTERNAL_FATAL = 'WORKER_INTERNAL_FATAL';
    public const CONFIGURATION_INVALID_GLOBAL = 'CONFIGURATION_INVALID_GLOBAL';

    private const STRUCTURAL_CODES = [
        self::PORTAL_STRUCTURE_CHANGED,
        self::LOGIN_ERROR,
        self::SESSION_LOST_UNRECOVERABLE,
        self::WORKER_INTERNAL_FATAL,
        self::CONFIGURATION_INVALID_GLOBAL,
    ];

    public function isStructural(?string $code): bool
    {
        return in_array($code, self::STRUCTURAL_CODES, true);
    }

    public function isStructuralResult(array $result): bool
    {
        $code = $result['error_code'] ?? $result['erro_codigo'] ?? null;

        if ($code === self::PORTAL_UNAVAILABLE) {
            return $this->portalUnavailableRetryExceeded($result);
        }

        return $this->isStructural($code);
    }

    public function label(?string $code): string
    {
        return match ($code) {
            self::PORTAL_STRUCTURE_CHANGED => 'Estrutura do portal alterada',
            self::LOGIN_ERROR => 'Login Unimed inválido',
            self::PORTAL_UNAVAILABLE => 'Portal Unimed indisponível',
            self::SESSION_LOST_UNRECOVERABLE => 'Sessão Unimed perdida sem recuperação',
            self::WORKER_INTERNAL_FATAL => 'Falha fatal interna do worker',
            self::CONFIGURATION_INVALID_GLOBAL => 'Configuração global inválida',
            'WORKER_UNAVAILABLE' => 'Worker indisponível',
            'TENANT_LOCK_UNAVAILABLE' => 'Automação concorrente bloqueada',
            default => $code ?: 'Erro não classificado',
        };
    }

    private function portalUnavailableRetryExceeded(array $result): bool
    {
        $attempt = (int) ($result['attempt'] ?? $result['attempts'] ?? $result['retry_count'] ?? 0);
        $maxAttempts = (int) ($result['max_attempts'] ?? 3);

        return $attempt >= $maxAttempts;
    }
}
