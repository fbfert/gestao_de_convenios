<?php

namespace App\Services\Automation;

class AutomationErrorCatalog
{
    public const PORTAL_STRUCTURE_CHANGED = 'PORTAL_STRUCTURE_CHANGED';

    public const LOGIN_ERROR = 'LOGIN_ERROR';

    public const PORTAL_UNAVAILABLE = 'PORTAL_UNAVAILABLE';

    public const SESSION_LOST_UNRECOVERABLE = 'SESSION_LOST_UNRECOVERABLE';

    public const WORKER_INTERNAL_FATAL = 'WORKER_INTERNAL_FATAL';

    public const CONFIGURATION_INVALID_GLOBAL = 'CONFIGURATION_INVALID_GLOBAL';

    /**
     * O convênio da execução não tem credencial de automação ativa.
     *
     * Fica FORA de STRUCTURAL_CODES de propósito: código estrutural manda o
     * disjuntor pausar a credencial, e pausar o que não existe — ou o que já
     * está pausado — seria circular. Isto é falta de configuração, e quem
     * resolve é o operador na tela de credenciais, não uma pausa automática.
     */
    public const CREDENTIAL_MISSING = 'CREDENTIAL_MISSING';

    private const STRUCTURAL_CODES = [
        self::PORTAL_STRUCTURE_CHANGED,
        self::LOGIN_ERROR,
        self::SESSION_LOST_UNRECOVERABLE,
        self::WORKER_INTERNAL_FATAL,
        self::CONFIGURATION_INVALID_GLOBAL,
    ];

    public function isStructural(?string $code): bool
    {
        return in_array($code, self::STRUCTURAL_CODES, true);
    }

    public function isStructuralResult(array $result): bool
    {
        $code = $result['error_code'] ?? $result['erro_codigo'] ?? null;

        if ($code === self::PORTAL_UNAVAILABLE) {
            return $this->portalUnavailableRetryExceeded($result);
        }

        return $this->isStructural($code);
    }

    public function label(?string $code): string
    {
        return match ($code) {
            self::PORTAL_STRUCTURE_CHANGED => 'Estrutura do portal alterada',
            self::LOGIN_ERROR => 'Login Unimed inválido',
            self::PORTAL_UNAVAILABLE => 'Portal Unimed indisponível',
            self::SESSION_LOST_UNRECOVERABLE => 'Sessão Unimed perdida sem recuperação',
            self::WORKER_INTERNAL_FATAL => 'Falha fatal interna do worker',
            self::CONFIGURATION_INVALID_GLOBAL => 'Configuração global inválida',
            self::CREDENTIAL_MISSING => 'Convênio sem credencial de automação ativa',
            'WORKER_UNAVAILABLE' => 'Worker indisponível',
            'TENANT_LOCK_UNAVAILABLE' => 'Automação concorrente bloqueada',

            /*
             * Finalização de guia na Unimed.
             *
             * Cada rótulo diz ao operador o que fazer, e não o que a função
             * retornou: o portal real nunca foi visto em desenvolvimento (não
             * há homologação da Unimed), então boa parte destes erros vai
             * aparecer pela primeira vez em produção e precisa se explicar
             * sozinha. Ver a spec `automacao-unimed-finalizar-guia`.
             */
            'NUMERO_GUIA_AUSENTE' => 'Guia sem número da operadora',
            'SEM_SESSOES_PARA_ENVIAR' => 'Guia sem sessões para enviar',
            'TELA_BUSCA_GUIA_NAO_ENCONTRADA' => 'Não achei a busca de guia no portal — confira o caminho configurado',
            'GUIA_NAO_ENCONTRADA_NO_PORTAL' => 'A busca no portal não devolveu esta guia',
            'TELA_EXECUCAO_NAO_ABRIU' => 'A tela de execução da guia não abriu no portal',
            'REGIME_ATENDIMENTO_RECUSADO' => 'O portal não aceitou o regime de atendimento',
            'TIPO_ATENDIMENTO_RECUSADO' => 'O portal não aceitou o tipo de atendimento',
            'QT_AUTORIZADA_NAO_LIDA' => 'Não consegui ler a quantidade autorizada no portal',
            'QT_AUTORIZADA_DIVERGENTE' => 'Quantidade autorizada diverge entre o Gescon e o portal',
            'SESSOES_ACIMA_DO_AUTORIZADO' => 'Há mais sessões registradas do que o portal autoriza',
            'SESSOES_ACIMA_DO_MAXIMO' => 'Mais sessões do que as dez que o portal aceita por guia',
            'DT_SERIE_CAMPO_INDISPONIVEL' => 'Campo de data da série indisponível no portal',
            'DT_SERIE_FORMATO_RECUSADO' => 'O portal recusou o formato da data da sessão',
            'ANEXO_SEM_CAMINHO' => 'Folha de registro sem arquivo no servidor',
            'ANEXO_BOTAO_NAO_ENCONTRADO' => 'Não achei o botão de anexos no portal',
            'ANEXO_NAO_CONFIRMADO' => 'O portal não confirmou o envio da folha',
            'BOTAO_GRAVAR_FINALIZAR_NAO_ENCONTRADO' => 'Não achei o botão Gravar e Finalizar no portal',
            'GRAVAR_FINALIZAR_RECUSADO' => 'O portal recusou a finalização',

            /*
             * Conferência de guias já finalizadas na operadora.
             *
             * `FILTRO_DATA_NAO_LIMPO` é o que mais importa dos três: sem ele, o
             * portal filtraria pelos últimos dias e devolveria "não finalizada"
             * para guias antigas que ESTÃO finalizadas — em lote, e sem nada
             * acusando. Ver conferirGuiaFinalizada.js.
             */
            'TELA_EXAMES_FINALIZADOS_NAO_ABRIU' => 'Não achei a tela de exames finalizados no portal',
            'FILTRO_DATA_NAO_LIMPO' => 'O portal repôs a data do filtro — a busca esconderia as guias antigas',

            default => $code ?: 'Erro não classificado',
        };
    }

    private function portalUnavailableRetryExceeded(array $result): bool
    {
        $attempt = (int) ($result['attempt'] ?? $result['attempts'] ?? $result['retry_count'] ?? 0);
        $maxAttempts = (int) ($result['max_attempts'] ?? 3);

        return $attempt >= $maxAttempts;
    }
}
