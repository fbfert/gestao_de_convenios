<?php

namespace App\Support;

class PermissionCatalog
{
    public const ALL = [
        'dashboard.convenios',
        'dashboard.solicitacoes',
        'dashboard.guias',
        'dashboard.antecipacoes',
        'dashboard.lancamentos',
        'dashboard.conciliacoes',
        'dashboard.pacientes',
        'dashboard.profissionais',
        'dashboard.medicos',
        'dashboard.especialidades',
        'dashboard.usuarios',
        'dashboard.analiticos',
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
        'manual.manage',
        'configuracoes.manage',
        'configuracoes.unimed.manage',
    ];

    public static function all(): array
    {
        return self::ALL;
    }
}
