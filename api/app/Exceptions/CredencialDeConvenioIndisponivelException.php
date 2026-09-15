<?php

namespace App\Exceptions;

use App\Services\Automation\AutomationErrorCatalog;
use RuntimeException;

/**
 * O convênio da execução não tem credencial de automação ativa.
 *
 * Falhar aqui, com código do catálogo, é melhor do que mandar o worker tentar
 * login sem credencial e depois classificar a resposta do portal: o motivo real
 * fica registrado, e o operador vê "convênio sem credencial" em vez de
 * "LOGIN_ERROR".
 *
 * `CREDENTIAL_MISSING` não é código estrutural, então isto não dispara o
 * disjuntor — pausar uma credencial que não existe seria circular.
 */
class CredencialDeConvenioIndisponivelException extends RuntimeException
{
    public function codigoDeAutomacao(): string
    {
        return AutomationErrorCatalog::CREDENTIAL_MISSING;
    }
}
