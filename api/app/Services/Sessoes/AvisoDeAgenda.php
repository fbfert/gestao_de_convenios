<?php

namespace App\Services\Sessoes;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Algo que vale dizer mas não vale barrar.
 *
 * Hoje só existe um: o executante já tem, naquele horário, sessão com outro
 * paciente. Quem está lançando a folha assinada raramente pode resolver a
 * agenda alheia, e o erro pode estar do outro lado — barrar aqui travaria o
 * lançamento certo por causa do errado.
 *
 * @implements Arrayable<string, mixed>
 */
final class AvisoDeAgenda implements Arrayable
{
    /** Mesmo profissional, mesmo horário, outro paciente. */
    public const TIPO_CHOQUE_PROFISSIONAL = 'choque_profissional';

    public function __construct(
        public readonly string $tipo,
        public readonly string $referencia,
        public readonly string $mensagem,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'referencia' => $this->referencia,
            'mensagem' => $this->mensagem,
        ];
    }
}
