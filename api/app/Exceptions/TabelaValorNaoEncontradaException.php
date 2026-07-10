<?php

namespace App\Exceptions;

use RuntimeException;

class TabelaValorNaoEncontradaException extends RuntimeException
{
    public static function forGuia(int $convenioId, int $especialidadeId, ?int $profissionalId): self
    {
        $profissional = $profissionalId ? (string) $profissionalId : 'null';

        return new self(
            "Nenhuma tabela de valor vigente encontrada para convenio_id={$convenioId}, especialidade_id={$especialidadeId}, profissional_id={$profissional}"
        );
    }
}
