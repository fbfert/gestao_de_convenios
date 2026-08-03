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

    public static function all(): array
    {
        return self::ALL;
    }
}
