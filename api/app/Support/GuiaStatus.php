<?php

namespace App\Support;

class GuiaStatus
{
    /**
     * Prefixo do numero de guia gerado internamente para convenio manual, em
     * SolicitacaoService::numeroGuiaDoItem(). O valor completo e por item
     * (`{prefixo}{solicitacao_id}-{item_id}`), e nao por solicitacao: uma guia
     * por item com um numero por solicitacao faria as N nascerem iguais.
     *
     * NAO E numero de operadora: e um valor de preenchimento, e a tela precisa
     * trata-lo como ausencia de numero. Vive aqui, e nao solto em cada arquivo,
     * porque a regra e a mesma no backend e no front — e string duplicada e como
     * essa distincao se perde na terceira tela que precisar dela. O front recebe
     * o valor pela API (SolicitacaoResource) em vez de repetir o literal.
     */
    public const PREFIXO_NUMERO_PLACEHOLDER = 'GUIA-SOLICITACAO-';

    /** O numero e um valor de preenchimento, e nao um numero da operadora? */
    public static function numeroEhPlaceholder(?string $numero): bool
    {
        return $numero !== null && str_starts_with($numero, self::PREFIXO_NUMERO_PLACEHOLDER);
    }

    /** O que a tela deve tratar como numero de verdade — nulo quando nao ha. */
    public static function numeroDaOperadora(?string $numero): ?string
    {
        return self::numeroEhPlaceholder($numero) ? null : ($numero ?: null);
    }

    public const UNDER_REVIEW = 'under_review';
    public const FINALIZED = 'finalized';
    public const APPROVED = 'approved';
    public const DENIED = 'denied';
    public const CANCELED = 'canceled';
    public const NEEDS_VERIFICATION = 'needs_verification';

    public const ALL = [
        self::UNDER_REVIEW,
        self::FINALIZED,
        self::APPROVED,
        self::DENIED,
        self::CANCELED,
        self::NEEDS_VERIFICATION,
    ];

    /**
     * Prefixo dos status de guia histórica (migração de planilha antiga,
     * nunca entra em automação — ver Guia::naoHistorica()). Guarda o
     * resultado real dentro do próprio status ("Histórico · Negado" em vez
     * de um "Histórico" genérico que perderia essa informação) — ver
     * ADR informal na conversa de 03/09/2026.
     */
    public const HISTORICO_PREFIX = 'historico_';

    public const ALL_HISTORICO = [
        self::HISTORICO_PREFIX.self::UNDER_REVIEW,
        self::HISTORICO_PREFIX.self::FINALIZED,
        self::HISTORICO_PREFIX.self::APPROVED,
        self::HISTORICO_PREFIX.self::DENIED,
        self::HISTORICO_PREFIX.self::CANCELED,
        self::HISTORICO_PREFIX.self::NEEDS_VERIFICATION,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function paraHistorico(string $status): string
    {
        return self::HISTORICO_PREFIX.$status;
    }

    public static function ehHistorico(string $status): bool
    {
        return str_starts_with($status, self::HISTORICO_PREFIX);
    }
}
