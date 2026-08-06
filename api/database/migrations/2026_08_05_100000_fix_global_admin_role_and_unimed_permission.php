<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Corrige a migration 2026_08_03_200001, que concedeu
 * `configuracoes.unimed.manage` sem definir o team id do Spatie.
 *
 * Efeito do defeito:
 * 1. os roles `admin` de cada tenant nunca receberam a permissao, entao os
 *    administradores tomam 403 nas telas de credencial e de mapeamentos Unimed;
 * 2. foi criado um role `admin` com tenant_id NULL. Em modo teams o Spatie
 *    resolve `whereNull(team) OR team = atual`, entao esse role global passa a
 *    sombrear o `admin` de todo tenant em `Role::findOrCreate`, some da tela de
 *    Permissoes e quebra o route binding por tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $permission = Permission::findOrCreate('configuracoes.unimed.manage', 'web');

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        $registrar->setPermissionsTeamId(null);

        $this->removerRoleAdminGlobalOrfao();

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permission = Permission::query()
            ->where('name', 'configuracoes.unimed.manage')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            foreach (Tenant::query()->get() as $tenant) {
                $registrar->setPermissionsTeamId($tenant->id);

                Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', 'admin')
                    ->where('guard_name', 'web')
                    ->get()
                    ->each(fn (Role $role) => $role->revokePermissionTo($permission));
            }
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        // O role `admin` global removido no up() nao e recriado: ele nunca
        // deveria ter existido, e recria-lo reintroduziria o defeito.
    }

    private function removerRoleAdminGlobalOrfao(): void
    {
        $tabelaVinculos = config('permission.table_names.model_has_roles') ?: 'model_has_roles';
        $colunaRole = config('permission.column_names.role_pivot_key') ?: 'role_id';

        Role::query()
            ->whereNull('tenant_id')
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($tabelaVinculos, $colunaRole) {
                $temUsuarios = DB::table($tabelaVinculos)
                    ->where($colunaRole, $role->getKey())
                    ->exists();

                if ($temUsuarios) {
                    return;
                }

                $role->permissions()->detach();
                $role->delete();
            });
    }
};
