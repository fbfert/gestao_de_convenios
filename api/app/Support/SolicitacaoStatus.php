<?php

namespace App\Support;

/**
 * Situacoes de Solicitacao, e as duas perguntas que se faz sobre elas.
 *
 * Existe porque a regra de "este item pode ser enviado a operadora?" precisava
 * valer nos DOIS lados: a tela escondia o botao e o backend conferia por conta
 * propria. Regra repetida em dois lugares e regra que diverge — e aqui a
 * divergencia seria botao habilitado com a API recusando, ou o contrario.
 *
 * O valor fica em ingles no banco e na API; a traducao mora so na interface
 * (openspec/config.yaml).
 */
class SolicitacaoStatus
{
    public const UNDER_REVIEW = 'under_review';
    public const READY_FOR_AUTOMATION = 'ready_for_automation';
    public const GUIA_GERADA = 'guia_gerada';
    public const APPROVED = 'approved';
    public const DENIED = 'denied';
    public const HISTORICO = 'historico';

    /**
     * Situacoes em que nenhum item vai para a operadora.
     *
     * `under_review` porque enviar antes da analise pularia a etapa que existe
     * para conferir o pedido. `denied` e `historico` porque enviar reabriria,
     * pela porta dos fundos, algo que foi encerrado.
     *
     * Repare no que NAO esta aqui: `approved` e `guia_gerada` permitem envio.
     * O gate e do ITEM, e uma solicitacao ja aprovada pode receber um item novo
     * — e o caso de uso de "Adicionar sessoes".
     */
    public const BLOQUEIAM_ENVIO = [
        self::UNDER_REVIEW,
        self::DENIED,
        self::HISTORICO,
    ];

    /**
     * Situacoes em que nao se acrescenta item.
     *
     * Deliberadamente DIFERENTE de BLOQUEIAM_ENVIO: `under_review` barra o
     * envio mas permite acrescentar, porque acrescentar antes da analise e o
     * fluxo normal de quem esta montando o pedido. O que nao se faz e mexer no
     * que ja foi encerrado — negado ou historico.
     *
     * As duas listas moram lado a lado de proposito: sao parecidas o bastante
     * para alguem reusar a errada se estiverem soltas em arquivos diferentes.
     */
    public const BLOQUEIAM_ADICAO = [
        self::DENIED,
        self::HISTORICO,
    ];

    /**
     * Situacoes cujo valor e DERIVADO das guias dos itens.
     *
     * As de fora sao decisao humana registrada (analise, negativa) ou rastro de
     * migracao (historico), e nenhuma sincronizacao pode sobrescreve-las.
     */
    public const DERIVADAS_DOS_ITENS = [
        self::READY_FOR_AUTOMATION,
        self::GUIA_GERADA,
        self::APPROVED,
    ];

    public static function bloqueiaEnvio(?string $status): bool
    {
        return $status === null || in_array($status, self::BLOQUEIAM_ENVIO, true);
    }

    public static function bloqueiaAdicao(?string $status): bool
    {
        return $status === null || in_array($status, self::BLOQUEIAM_ADICAO, true);
    }

    public static function derivaDosItens(?string $status): bool
    {
        return $status !== null && in_array($status, self::DERIVADAS_DOS_ITENS, true);
    }
}
