<?php

namespace App\Concerns;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Support\TenantContext;

/**
 * Aplicar em todo Model de negócio, exceto Tenant e User (ver nota no
 * App\Models\User sobre o caso especial de autenticação).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (is_null($model->tenant_id) && TenantContext::get()) {
                $model->tenant_id = TenantContext::get();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
