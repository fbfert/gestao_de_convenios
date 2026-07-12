<?php

namespace App\Support;

class PermissionCatalog
{
    public const ALL = [
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

    public static function all(): array
    {
        return self::ALL;
    }
}
