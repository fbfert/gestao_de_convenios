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
            'antecipacoes.manage',
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
            'configuracoes.clinica.manage',
        ],
        // As permissoes solicitacoes.manage/guias.manage/lancamentos.manage/
        // antecipacoes.manage controlam a EDICAO manual desses 4 cadastros
        // (nao a criacao normal, que continua liberada por .view/.viewOwn) e
        // ficam de fora daqui de proposito: por padrao, so o admin corrige
        // dados ja registrados. A clinica pode conceder ao funcionario depois,
        // em Perfis e Permissoes, se quiser.
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
            'guias.view',
            'guias.viewOwn',
            'antecipacoes.view',
            'antecipacoes.viewOwn',
            'lancamentos.view',
            'lancamentos.viewOwn',
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

    /**
     * Papel de sistema: não pode ser renomeado nem excluído, mas as permissões
     * dele seguem editáveis. `profissional` carrega significado no código — é
     * o papel que amarra o usuário ao cadastro de profissional — e `admin` é a
     * rede de segurança de acesso. Renomear qualquer um dos dois quebraria
     * comportamento que não aparece na tela.
     */
    public static function ehDeSistema(string $papel): bool
    {
        return array_key_exists($papel, self::PADRAO);
    }
}
