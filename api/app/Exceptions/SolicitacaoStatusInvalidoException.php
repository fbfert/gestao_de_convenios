<?php

namespace App\Exceptions;

use RuntimeException;

class SolicitacaoStatusInvalidoException extends RuntimeException
{
    public static function transicaoInvalida(string $statusAtual, string $statusDestino): self
    {
        return new self("Transição inválida de solicitação: {$statusAtual} -> {$statusDestino}.");
    }
}
