<?php

namespace App\Exceptions;

use RuntimeException;

class GuiaStatusInvalidoException extends RuntimeException
{
    public static function finalizacaoRequerDados(): self
    {
        return new self('Para finalizar a guia, senha e validade_senha são obrigatórias.');
    }

    public static function transicaoInvalida(string $statusAtual, string $statusDestino): self
    {
        return new self("Transição inválida de guia: {$statusAtual} -> {$statusDestino}.");
    }
}
