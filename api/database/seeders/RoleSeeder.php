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
                'dashboard.convenios',
                'dashboard.solicitacoes',
                'dashboard.guias',
                'dashboard.antecipacoes',
                'dashboard.lancamentos',
                'dashboard.conciliacoes',
                'dashboard.pacientes',
                'dashboard.profissionais',
                'dashboard.medicos',
                'dashboard.usuarios',
                'dashboard.auditoria',
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
                'profissionais.manage',
                'medicos.manage',
                'especialidades.manage',
                'usuarios.manage',
                'convenios.manage',
                'permissoes.manage',
            ],
            'funcionario' => [
                'dashboard.convenios',
                'dashboard.solicitacoes',
                'dashboard.guias',
                'dashboard.antecipacoes',
                'dashboard.lancamentos',
                'dashboard.conciliacoes',
                'dashboard.pacientes',
                'dashboard.profissionais',
                'dashboard.medicos',
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
                'profissionais.manage',
                'medicos.manage',
            ],
            'profissional' => [
                'dashboard.guias',
                'dashboard.antecipacoes',
                'dashboard.lancamentos',
                'dashboard.conciliacoes',
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
