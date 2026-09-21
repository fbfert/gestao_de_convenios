<?php

namespace App\Services\Sessoes;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Um motivo concreto para não gravar uma sessão, já escrito para ser lido por
 * quem está com a folha na mão.
 *
 * Carrega sempre os dois lados: a sessão que está entrando e aquela contra a
 * qual ela bateu. Sem o outro lado a mensagem vira "conflito" e o operador
 * não tem o que corrigir.
 *
 * @implements Arrayable<string, mixed>
 */
final class ConflitoDeAgenda implements Arrayable
{
    /** Menos de 50 minutos entre o início de duas sessões do mesmo paciente. */
    public const TIPO_INTERVALO = 'intervalo';

    /** Duas sessões do paciente começando na mesma data e hora. */
    public const TIPO_MESMO_HORARIO = 'mesmo_horario';

    /** Mais sessões da especialidade no dia do que ela permite. */
    public const TIPO_LIMITE_DIARIO = 'limite_diario';

    public function __construct(
        public readonly string $tipo,
        public readonly string $referencia,
        public readonly ?string $referenciaOutra,
        public readonly string $mensagem,
        public readonly ?int $guiaId = null,
        public readonly ?string $guiaNumero = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'referencia' => $this->referencia,
            'referencia_outra' => $this->referenciaOutra,
            'mensagem' => $this->mensagem,
            'guia_id' => $this->guiaId,
            'guia_numero' => $this->guiaNumero,
        ];
    }
}
