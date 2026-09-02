<?php

namespace App\Support;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Helpers puros (sem estado) pro "acesso de super admin" a outro tenant —
 * ver App\Models\User::getTenantIdAttribute()/hasPermissionTo() e
 * App\Http\Controllers\TenantController::acessar().
 *
 * Desenho: um super admin que clica "Acessar" numa clínica ganha um TOKEN
 * NOVO (a própria conta, não login como outra pessoa), marcado via ability
 * com o tenant-alvo. Enquanto esse token for o usado na requisição,
 * `$user->tenant_id` (e portanto TenantContext, PermissionRegistrar,
 * BelongsToTenant, e todo `$request->user()->tenant_id` espalhado pelos
 * controllers) passa a apontar pro tenant-alvo — sem precisar caçar cada um
 * dos lugares que lê essa propriedade.
 */
class SuperAdminAcesso
{
    private const PREFIXO_ABILITY = 'tenant-acesso:';

    public static function abilityPara(int $tenantId): string
    {
        return self::PREFIXO_ABILITY.$tenantId;
    }

    /** Lê o tenant-alvo gravado nas abilities do token de acesso, se houver. */
    public static function tenantIdDoToken(?PersonalAccessToken $token): ?int
    {
        if (! $token) {
            return null;
        }

        foreach ($token->abilities ?? [] as $ability) {
            if (str_starts_with($ability, self::PREFIXO_ABILITY)) {
                return (int) substr($ability, strlen(self::PREFIXO_ABILITY));
            }
        }

        return null;
    }
}
