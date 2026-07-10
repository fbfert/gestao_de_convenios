<?php

namespace App\Exceptions;

use RuntimeException;

class AntecipacaoCotaEsgotadaException extends RuntimeException
{
    public static function forAntecipacao(int $antecipacaoId): self
    {
        return new self("A antecipação {$antecipacaoId} já está fechada ou com a cota esgotada.");
    }
}
