<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $roles = [
            'admin' => [
                'solicitacoes.view',
                'solicitacoes.manage',
                'guias.view',
                'guias.viewOwn',
                'guias.manage',
                'antecipacoes.view',
                'antecipacoes.viewOwn',
                'lancamentos.view',
                'lancamentos.viewOwn',
                'lancamentos.manage',
                'conciliacoes.view',
                'conciliacoes.viewOwn',
                'conciliacoes.manage',
                'medicos.manage',
                'usuarios.manage',
                'convenios.manage',
                'permissoes.manage',
            ],
            'funcionario' => [
                'solicitacoes.view',
                'solicitacoes.manage',
                'guias.view',
                'guias.viewOwn',
                'guias.manage',
                'antecipacoes.view',
                'antecipacoes.viewOwn',
                'lancamentos.view',
                'lancamentos.viewOwn',
                'lancamentos.manage',
                'conciliacoes.view',
                'conciliacoes.viewOwn',
                'conciliacoes.manage',
                'medicos.manage',
            ],
            'profissional' => [
                'guias.viewOwn',
                'antecipacoes.viewOwn',
                'lancamentos.viewOwn',
                'conciliacoes.viewOwn',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
