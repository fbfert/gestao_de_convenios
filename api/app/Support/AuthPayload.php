<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bloco de identidade devolvido no login e em GET /user.
 *
 * Vive aqui, e não dentro do AuthController, porque duas rotas precisam
 * exatamente do mesmo formato: o login entrega o payload uma vez, e GET /user
 * é o que o frontend consulta a cada abertura para descobrir que as permissões
 * do papel mudaram. Formatos divergentes fariam a segunda leitura apagar
 * campos que a primeira tinha trazido.
 */
class AuthPayload
{
    /**
     * @param  int|null  $tenantIdEfetivo  Forçado por quem chama (ex.:
     *     TenantController::acessar, que ainda está autenticado pelo token
     *     de ORIGEM — o token novo, marcado com o tenant-alvo, só passa a
     *     existir depois desta chamada, então `$user->tenant_id` ainda não
     *     reflete o acesso). Quando omitido, usa `$user->tenant_id`, que já
     *     se autocorrige durante um acesso em andamento (ver
     *     User::getTenantIdAttribute()).
     */
    public static function paraUsuario(User $user, ?int $tenantIdEfetivo = null): array
    {
        $tenantIdCasa = (int) $user->getRawOriginal('tenant_id');
        $tenantId = $tenantIdEfetivo ?? (int) $user->tenant_id;
        $ehAcessoEstranho = $tenantId !== $tenantIdCasa;

        // Sem fixar o team id, o Spatie resolveria papel e permissões no
        // contexto de quem chamou — no login ainda não há tenant no contexto.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $user->unsetRelation('roles')->unsetRelation('permissions');

        $tenantEfetivo = Tenant::query()->find($tenantId);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // Acessando outro tenant como super admin, o papel Spatie
            // resolvido dá vazio (o super admin não tem papel atribuído lá) —
            // o rótulo 'admin' aqui é só o que a tela usa pra liberar UI (ex.:
            // ManualPage), coerente com o bypass em User::hasPermissionTo().
            'role' => $ehAcessoEstranho ? 'admin' : ($user->roles()->first()?->name ?? $user->getRoleNames()->first()),
            'permissions' => $ehAcessoEstranho
                ? PermissionCatalog::all()
                : $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            // Só para o menu decidir se mostra a gestão de tenants. A
            // restrição de verdade é o middleware `super-admin`; este campo
            // apenas evita exibir um item que responderia 403.
            'super_admin' => $user->ehSuperAdmin(),
            'tenant' => [
                'id' => $tenantEfetivo?->id,
                'nome' => $tenantEfetivo?->nome,
                'slug' => $tenantEfetivo?->slug,
            ],
            // Sinaliza pro frontend que este payload é de um super admin
            // atuando fora da própria clínica — usado pra manter a faixa de
            // aviso e o botão "Voltar" coerentes após um refresh de página.
            'acesso_super_admin' => $ehAcessoEstranho,
        ];
    }
}
