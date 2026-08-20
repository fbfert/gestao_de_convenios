<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Corrige a 2026_08_20_180004: mesmo defeito documentado em
 * 2026_08_05_100000_fix_global_admin_role_and_unimed_permission.php — conceder
 * permissão sem `setPermissionsTeamId` cria (ou usa) um role `admin` global
 * (tenant_id NULL) que os administradores de verdade não têm, e some da tela
 * de Permissões (Spatie em modo teams resolve tenant_id atual OU NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $permission = Permission::findOrCreate('configuracoes.clinica.manage', 'web');

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

        $this->removerRoleAdminGlobalOrfaoSeNaoUsado();

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permission = Permission::query()
            ->where('name', 'configuracoes.clinica.manage')
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
    }

    /** Mesma checagem da migration original: só apaga o role global se ninguém estiver nele. */
    private function removerRoleAdminGlobalOrfaoSeNaoUsado(): void
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
