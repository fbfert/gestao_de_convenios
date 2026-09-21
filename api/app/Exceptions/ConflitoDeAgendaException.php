<?php

namespace App\Exceptions;

use App\Services\Sessoes\ResultadoDeAgenda;
use RuntimeException;

/**
 * Sessões que não podem coexistir na agenda do paciente.
 *
 * Tem exceção própria, e não `ValidationException`, porque a tela precisa de
 * mais do que uma lista de mensagens: precisa saber QUAL linha da grade está
 * em conflito e contra o quê, para marcar a linha e deixar o operador
 * corrigir ali mesmo. `conflitos` carrega isso.
 *
 * Diferente da divergência de paciente, aqui não existe caminho de "confirmar
 * assim mesmo" — ver a spec `sessoes-regras-de-agenda`. Por isso a exceção não
 * tem nenhum campo de justificativa: não há o que justificar.
 */
class ConflitoDeAgendaException extends RuntimeException
{
    public function __construct(
        public readonly ResultadoDeAgenda $resultado,
    ) {
        parent::__construct('Há sessões em conflito na agenda do paciente.');
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'message' => $this->getMessage(),
            'conflitos' => array_map(
                fn ($conflito) => $conflito->toArray(),
                $this->resultado->conflitos,
            ),
            'avisos' => array_map(
                fn ($aviso) => $aviso->toArray(),
                $this->resultado->avisos,
            ),
            // As mesmas mensagens no formato de erro de validação, para quem
            // (ou o que) só sabe ler `errors`.
            'errors' => ['sessoes' => $this->resultado->mensagens()],
        ];
    }
}
