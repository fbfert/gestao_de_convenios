<?php

namespace App\Support;

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
    public static function paraUsuario(User $user): array
    {
        // Sem fixar o team id, o Spatie resolveria papel e permissões no
        // contexto de quem chamou — no login ainda não há tenant no contexto.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles()->first()?->name ?? $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            // Só para o menu decidir se mostra a gestão de tenants. A
            // restrição de verdade é o middleware `super-admin`; este campo
            // apenas evita exibir um item que responderia 403.
            'super_admin' => $user->ehSuperAdmin(),
            'tenant' => [
                'id' => $user->tenant?->id,
                'nome' => $user->tenant?->nome,
                'slug' => $user->tenant?->slug,
            ],
        ];
    }
}
