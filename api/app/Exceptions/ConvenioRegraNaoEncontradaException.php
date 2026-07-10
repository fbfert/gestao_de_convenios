<?php

namespace App\Exceptions;

use RuntimeException;

class ConvenioRegraNaoEncontradaException extends RuntimeException
{
    public static function forGuia(int $convenioId, string $tipoTerapia): self
    {
        return new self("Nenhuma regra vigente encontrada para convenio_id={$convenioId} e tipo_terapia={$tipoTerapia}.");
    }
}
