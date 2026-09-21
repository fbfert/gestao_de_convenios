<?php

namespace App\Exceptions;

use RuntimeException;

class GuiaStatusInvalidoException extends RuntimeException
{
    public static function finalizacaoRequerDados(): self
    {
        return new self('Para finalizar a guia, senha e validade_senha são obrigatórias.');
    }

    public static function finalizacaoRequerSessoes(): self
    {
        return new self('Para finalizar a guia, é necessário ter ao menos uma sessão registrada.');
    }

    /**
     * Guia de convênio com automação Unimed não finaliza à mão.
     *
     * Finalizar no Gescon passou a significar "a operadora aceitou": marcar
     * finalizada aqui sem ter finalizado lá criaria uma guia que o sistema dá
     * por encerrada e o portal continua esperando.
     */
    public static function finalizacaoExigeOperadora(): self
    {
        return new self(
            'Guia de convênio Unimed é finalizada na operadora, pelo botão "Finalizar na Unimed" na tela de Sessões.'
        );
    }

    public static function transicaoInvalida(string $statusAtual, string $statusDestino): self
    {
        return new self("Transição inválida de guia: {$statusAtual} -> {$statusDestino}.");
    }
}
