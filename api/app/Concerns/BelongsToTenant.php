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

    /**
     * Resolve `{modelo}` da URL já preso ao tenant de quem está autenticado.
     *
     * Por que aqui, e não no `TenantScope`: o `SubstituteBindings` roda ANTES
     * do `ResolveTenant` no grupo `api`, então no instante em que o Laravel
     * resolve o parâmetro da rota o `TenantContext` ainda está vazio — e o
     * `TenantScope` é no-op sem contexto. O binding implícito devolvia, nesse
     * instante, o registro de QUALQUER clínica.
     *
     * A compensação até aqui eram `Route::bind` manuais, um por parâmetro.
     * Funciona para os que alguém lembrou de registrar; todo parâmetro novo
     * nascia vazando. Isto inverte o padrão: o isolamento passa a ser o
     * comportamento por omissão de todo model que usa o trait, e esquecer
     * deixa de ser uma porta aberta.
     *
     * Não depende do `TenantContext` de propósito — lê o tenant direto do
     * usuário autenticado, que já existe nesse ponto do pipeline (o
     * `auth:sanctum` roda antes). Sem usuário, `tenant_id` fica nulo, não casa
     * com nada e o resultado é 404: falha fechada.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->aplicarTenantNaResolucao(parent::resolveRouteBinding($value, $field));
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->aplicarTenantNaResolucao(
            parent::resolveChildRouteBinding($childType, $value, $field)
        );
    }

    /**
     * Confere o tenant do que foi resolvido, em vez de refazer a consulta:
     * preserva qualquer `$field` customizado e a semântica de binding filho,
     * sem duplicar a lógica do Laravel.
     */
    private function aplicarTenantNaResolucao(mixed $resolvido): mixed
    {
        if (! $resolvido) {
            return null;
        }

        $tenantDoUsuario = request()->user()?->tenant_id;

        if ($tenantDoUsuario === null) {
            return null;
        }

        return (int) $resolvido->tenant_id === (int) $tenantDoUsuario ? $resolvido : null;
    }
}
