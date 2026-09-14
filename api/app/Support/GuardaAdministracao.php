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

    /**
     * Impede que quem administra usuários se promova, ou promova alguém acima
     * do próprio nível.
     *
     * `usuarios.manage` é do `admin` por padrão, mas a tela de Perfis e
     * Permissões permite concedê-la a qualquer papel — e o próprio catálogo
     * documenta isso como esperado ("a clínica pode conceder ao funcionário
     * depois"). Bastava a secretária ganhar essa permissão para cadastrar
     * usuários e, na requisição seguinte, atribuir `admin` a si mesma: passava
     * a administrar permissões, configurações e a trocar a senha de qualquer
     * pessoa do tenant — sem precisar da senha atual de ninguém.
     *
     * Duas travas, portanto: ninguém muda o próprio papel, e ninguém concede um
     * papel que carregue permissão que ele mesmo não tem.
     *
     * Super admin não esbarra aqui: o `can()` dele responde verdadeiro para
     * todo o catálogo, então o conjunto sempre contém o que está sendo dado.
     */
    public static function aoAtribuirPapel(User $alvo, string $papelNovo, ?User $autor): void
    {
        if (! $autor) {
            return;
        }

        $papelAtual = $alvo->roles->first()?->name;

        if ($papelAtual === $papelNovo) {
            return;
        }

        if ($alvo->getKey() === $autor->getKey()) {
            throw ValidationException::withMessages([
                'role' => 'Você não pode alterar o seu próprio papel. Peça a outra pessoa com permissão.',
            ]);
        }

        $permissoesDoPapel = Role::query()
            ->where('tenant_id', $alvo->tenant_id)
            ->where('name', $papelNovo)
            ->with('permissions')
            ->first()
            ?->permissions
            ->pluck('name')
            ->all() ?? [];

        $acima = array_values(array_filter(
            $permissoesDoPapel,
            fn (string $permissao) => ! $autor->can($permissao),
        ));

        if ($acima !== []) {
            throw ValidationException::withMessages([
                'role' => 'Este papel tem permissões que você não possui, então você não pode atribuí-lo: '
                    .implode(', ', $acima).'.',
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
