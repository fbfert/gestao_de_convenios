<?php

namespace App\Support;

class GuiaStatus
{
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
