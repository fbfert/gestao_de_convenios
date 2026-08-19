<?php

namespace App\Support;

/**
 * Catálogo fixo de permissões do sistema, com o rótulo que a tela mostra.
 *
 * O rótulo mora junto do nome técnico de propósito: enquanto viveu só no
 * frontend, o mapa ficou incompleto e a tela de permissões passou a exibir
 * `dashboard.convenios` cru para o administrador decidir. Permissão nova aqui
 * já nasce com texto legível.
 *
 * As permissões `dashboard.*` valem tanto para o bloco do painel quanto para a
 * visibilidade do item no menu — por isso o rótulo delas começa com "Ver".
 */
class PermissionCatalog
{
    public const ROTULOS = [
        'dashboard.convenios' => 'Ver Convênios',
        'dashboard.solicitacoes' => 'Ver Solicitações',
        'dashboard.guias' => 'Ver Guias',
        'dashboard.antecipacoes' => 'Ver Antecipações',
        'dashboard.lancamentos' => 'Ver Sessões',
        'dashboard.conciliacoes' => 'Ver Conciliações',
        'dashboard.pacientes' => 'Ver Pacientes',
        'dashboard.profissionais' => 'Ver Profissionais',
        'dashboard.medicos' => 'Ver Médicos',
        'dashboard.especialidades' => 'Ver Especialidades',
        'dashboard.usuarios' => 'Ver Usuários',
        'dashboard.analiticos' => 'Ver Analíticos',
        'dashboard.auditoria' => 'Ver Logs de Auditoria',
        'solicitacoes.view' => 'Abrir solicitações',
        'solicitacoes.manage' => 'Editar dados de solicitações',
        'guias.view' => 'Abrir guias de toda a clínica',
        'guias.viewOwn' => 'Abrir apenas as próprias guias',
        'guias.manage' => 'Editar dados de guias',
        'antecipacoes.view' => 'Abrir antecipações de toda a clínica',
        'antecipacoes.viewOwn' => 'Abrir apenas as próprias antecipações',
        'antecipacoes.manage' => 'Editar dados de antecipações',
        'lancamentos.view' => 'Abrir sessões de toda a clínica',
        'lancamentos.viewOwn' => 'Abrir apenas as próprias sessões',
        'lancamentos.manage' => 'Editar dados de sessões',
        'conciliacoes.view' => 'Abrir conciliações de toda a clínica',
        'conciliacoes.viewOwn' => 'Abrir apenas as próprias conciliações',
        'conciliacoes.manage' => 'Executar e alterar conciliações',
        'profissionais.manage' => 'Cadastrar e alterar profissionais',
        'medicos.manage' => 'Cadastrar e alterar médicos',
        'especialidades.manage' => 'Cadastrar e alterar especialidades',
        'usuarios.manage' => 'Cadastrar e alterar usuários',
        'convenios.manage' => 'Cadastrar convênios, regras e valores',
        'permissoes.manage' => 'Administrar papéis e permissões',
        'manual.manage' => 'Editar o manual do sistema',
        'configuracoes.manage' => 'Alterar configurações do sistema',
        'configuracoes.unimed.manage' => 'Configurar a automação da Unimed',
    ];

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::ROTULOS);
    }

    public static function rotuloDe(string $permissao): string
    {
        return self::ROTULOS[$permissao] ?? $permissao;
    }
}
