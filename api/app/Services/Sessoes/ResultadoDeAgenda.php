<?php

namespace App\Services\Sessoes;

use Illuminate\Contracts\Support\Arrayable;

/**
 * O que o avaliador de agenda tem a dizer sobre um conjunto de sessões.
 *
 * Conflito e aviso andam separados de propósito: um impede gravar, o outro
 * só informa. Misturá-los numa lista só levaria, mais cedo ou mais tarde, a
 * alguém tratar os dois igual.
 *
 * @implements Arrayable<string, mixed>
 */
final class ResultadoDeAgenda implements Arrayable
{
    /**
     * @param  array<int, ConflitoDeAgenda>  $conflitos
     * @param  array<int, AvisoDeAgenda>  $avisos
     */
    public function __construct(
        public readonly array $conflitos,
        public readonly array $avisos,
    ) {
    }

    public function temConflito(): bool
    {
        return $this->conflitos !== [];
    }

    /** @return array<int, string> */
    public function mensagens(): array
    {
        return array_map(
            static fn (ConflitoDeAgenda $conflito): string => $conflito->mensagem,
            $this->conflitos,
        );
    }

    public function toArray(): array
    {
        return [
            'conflitos' => array_map(
                static fn (ConflitoDeAgenda $conflito): array => $conflito->toArray(),
                $this->conflitos,
            ),
            'avisos' => array_map(
                static fn (AvisoDeAgenda $aviso): array => $aviso->toArray(),
                $this->avisos,
            ),
        ];
    }
}
