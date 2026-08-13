<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Impede que uma clínica se tranque para fora do próprio sistema.
 *
 * Sem isso, dois cliques inocentes na tela de permissões deixam o tenant sem
 * nenhum papel capaz de administrar permissões, e a única saída é acesso ao
 * banco de produção. Como o papel `admin` pode ter as permissões alteradas
 * (só o nome é protegido), não dá para confiar que ele sempre será a rede de
 * segurança.
 */
class GuardaAdministracao
{
    public const PERMISSAO = 'permissoes.manage';

    /**
     * @param  string[]  $permissoesFinais  permissões que o papel terá depois da alteração
     */
    public static function aoSincronizarPermissoes(Role $papel, array $permissoesFinais, ?User $autor): void
    {
        $manteria = in_array(self::PERMISSAO, $permissoesFinais, true);

        if (! $manteria && $autor && $autor->hasRole($papel->name)) {
            throw ValidationException::withMessages([
                'permissions' => 'Você não pode remover a administração de permissões do seu próprio papel.',
            ]);
        }

        if ($manteria) {
            return;
        }

        if (self::outrosPapeisComAdministracao($papel) === 0) {
            throw ValidationException::withMessages([
                'permissions' => 'Este é o último papel que administra permissões. Conceda a permissão a outro papel antes de retirá-la daqui.',
            ]);
        }
    }

    public static function aoExcluirPapel(Role $papel): void
    {
        if (! $papel->hasPermissionTo(self::PERMISSAO)) {
            return;
        }

        if (self::outrosPapeisComAdministracao($papel) === 0) {
            throw ValidationException::withMessages([
                'name' => 'Este é o último papel que administra permissões e por isso não pode ser excluído.',
            ]);
        }
    }

    private static function outrosPapeisComAdministracao(Role $papel): int
    {
        return Role::query()
            ->where('tenant_id', $papel->tenant_id)
            ->where('guard_name', $papel->guard_name)
            ->whereKeyNot($papel->getKey())
            ->whereHas('permissions', fn ($query) => $query->where('name', self::PERMISSAO))
            ->count();
    }
}
