<?php

namespace App\Support;

/**
 * Vocabulário da trilha: tipo, rótulo da ação e rótulo da entidade.
 *
 * A tela precisa oferecer "Alteração" e "Acesso negado" no filtro, não
 * `updated` e `acesso.negado`. O mapa vive aqui, e não no frontend, para que o
 * filtro por tipo e o rótulo exibido saiam da mesma fonte — se divergissem, o
 * seletor prometeria um recorte e a consulta devolveria outro.
 */
class AuditoriaCatalogo
{
    public const TIPOS = [
        'acesso' => 'Acesso',
        'criacao' => 'Criação',
        'alteracao' => 'Alteração',
        'exclusao' => 'Exclusão',
        'importacao' => 'Importação',
        'manutencao' => 'Manutenção',
    ];

    private const ROTULOS_ACAO = [
        'created' => 'Criação',
        'updated' => 'Alteração',
        'deleted' => 'Exclusão',
        'acesso.login' => 'Login',
        'acesso.logout' => 'Logout',
        'acesso.login_recusado' => 'Login recusado',
        'acesso.negado' => 'Acesso negado',
        'papel.criado' => 'Papel criado',
        'papel.renomeado' => 'Papel renomeado',
        'papel.excluido' => 'Papel excluído',
        'papel.permissoes_alteradas' => 'Permissões do papel alteradas',
        'analitico.importado' => 'Analítico importado',
        'auditoria.expurgada' => 'Auditoria expurgada',
        'unimed_rda_settings.updated' => 'Configuração da Unimed alterada',
        'unimed_rda.automation_paused' => 'Automação da Unimed pausada',
        'unimed_rda.automation_reactivated' => 'Automação da Unimed reativada',
    ];

    private const ROTULOS_ENTIDADE = [
        'ai_openai_settings' => 'Configurações de IA',
        'ai_prompt_templates' => 'Prompts operacionais',
        'analitico_unimed_lotes' => 'Analíticos',
        'antecipacoes' => 'Antecipações',
        'audit_logs' => 'Auditoria',
        'conciliacoes_financeiras' => 'Conciliações',
        'configuracoes_globais' => 'Configurações globais',
        'convenio_regras' => 'Regras de convênio',
        'convenios' => 'Convênios',
        'email_smtp_settings' => 'Envio de e-mails',
        'email_templates' => 'Templates de e-mail',
        'especialidades' => 'Especialidades',
        'guias' => 'Guias',
        'lancamentos' => 'Sessões',
        'lancamento_print_templates' => 'Templates de impressão',
        'manuais' => 'Manual',
        'medicos' => 'Médicos',
        'movimentos_financeiros' => 'Movimentos financeiros',
        'pacientes' => 'Pacientes',
        'profissionais' => 'Profissionais',
        'roles' => 'Papéis',
        'solicitacoes' => 'Solicitações',
        'solicitacao_itens' => 'Itens da solicitação',
        'solicitacao_documentos' => 'Documentos da solicitação',
        'tabela_valores' => 'Tabela de valores',
        'unimed_rda_credentials' => 'Credenciais da Unimed',
        'users' => 'Usuários',
    ];

    /**
     * Tipo da ação, por regra e não por lista fechada: ação nova nasce
     * classificada, em vez de cair silenciosamente fora de todos os filtros.
     */
    public static function tipoDe(string $acao): string
    {
        return match (true) {
            str_starts_with($acao, 'acesso.') => 'acesso',
            str_starts_with($acao, 'auditoria.') => 'manutencao',
            $acao === 'created', str_ends_with($acao, '.criado') => 'criacao',
            $acao === 'deleted', str_ends_with($acao, '.excluido') => 'exclusao',
            str_ends_with($acao, '.importado') => 'importacao',
            default => 'alteracao',
        };
    }

    public static function rotuloAcao(string $acao): string
    {
        return self::ROTULOS_ACAO[$acao] ?? $acao;
    }

    public static function rotuloEntidade(string $entidade): string
    {
        return self::ROTULOS_ENTIDADE[$entidade] ?? $entidade;
    }
}
