<?php

namespace App\Support;

/**
 * Papéis padrão de um tenant e as permissões de cada um.
 *
 * Vive aqui, e não no RoleSeeder, porque dois caminhos precisam da mesma
 * definição: o seeder (ambiente local e banco de testes) e a criação de tenant
 * pela tela de gestão. Duplicar o mapa faria uma clínica nova nascer com um
 * conjunto de permissões diferente do que o seeder entrega, e a divergência só
 * apareceria quando alguém reclamasse de um menu faltando.
 *
 * O catálogo de permissões em si é PermissionCatalog. Aqui é só a atribuição
 * inicial papel → permissões; depois disso quem manda é a tela de Permissões.
 */
class RoleCatalog
{
    public const PADRAO = [
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
            'dashboard.especialidades',
            'dashboard.analiticos',
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

    /** @return array<string, string[]> */
    public static function all(): array
    {
        return self::PADRAO;
    }

    /** @return string[] */
    public static function nomes(): array
    {
        return array_keys(self::PADRAO);
    }

    /** @return string[] */
    public static function permissoesDe(string $papel): array
    {
        return self::PADRAO[$papel] ?? [];
    }
}
