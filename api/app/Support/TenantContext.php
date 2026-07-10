<?php

namespace App\Support;

/**
 * Guarda o tenant_id da requisição atual em memória (por request).
 * Alimentado por App\Http\Middleware\ResolveTenant a partir do usuário
 * autenticado — nunca o contrário.
 */
class TenantContext
{
    protected static ?int $tenantId = null;

    public static function set(?int $tenantId): void
    {
        static::$tenantId = $tenantId;
    }

    public static function get(): ?int
    {
        return static::$tenantId;
    }

    public static function clear(): void
    {
        static::$tenantId = null;
    }
}
