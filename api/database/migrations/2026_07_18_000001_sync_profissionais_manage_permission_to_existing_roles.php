<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            $permission = Permission::findOrCreate('profissionais.manage', 'web');

            Role::findOrCreate('admin', 'web')->givePermissionTo($permission);
            Role::findOrCreate('funcionario', 'web')->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function down(): void
    {
        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            $permission = Permission::query()
                ->where('name', 'profissionais.manage')
                ->where('guard_name', 'web')
                ->first();

            if ($permission) {
                Role::findOrCreate('admin', 'web')->revokePermissionTo($permission);
                Role::findOrCreate('funcionario', 'web')->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
};
