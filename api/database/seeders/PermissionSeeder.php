<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
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
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
