<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\RoleCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // O mapa vive em App\Support\RoleCatalog: a criacao de tenant pela
        // tela de gestao usa exatamente o mesmo, para uma clinica nova nao
        // nascer com permissoes diferentes das que o seeder entrega.
        $roles = RoleCatalog::all();

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
