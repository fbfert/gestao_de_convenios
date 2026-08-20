<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('configuracoes.clinica.manage', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo($permission);
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', 'configuracoes.clinica.manage')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            Role::findOrCreate('admin', 'web')->revokePermissionTo($permission);
        }
    }
};
