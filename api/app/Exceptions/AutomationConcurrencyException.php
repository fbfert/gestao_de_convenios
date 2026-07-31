<?php

namespace App\Exceptions;

use RuntimeException;

class AutomationConcurrencyException extends RuntimeException
{
    public function __construct(public readonly int $execucaoId)
    {
        parent::__construct('Já existe automação ativa para este tenant e operação.');
    }
}
