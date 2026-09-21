<?php

namespace App\Services\Sessoes;

use App\Models\Lancamento;
use Carbon\CarbonImmutable;

/**
 * Uma sessão sendo avaliada pelas regras de agenda — venha ela da grade de
 * conferência da folha, do cadastro avulso, da edição de uma sessão já
 * gravada ou do pré-voo da finalização na operadora.
 *
 * Os quatro caminhos precisam ser comparados entre si e contra o que já está
 * no banco, então todos chegam ao avaliador nesta mesma forma. `referencia` é
 * como quem chamou reconhece a sessão de volta no resultado: o índice da linha
 * na grade, o id do lançamento, o que fizer sentido para a tela.
 */
final class SessaoCandidata
{
    public function __construct(
        public readonly string $referencia,
        public readonly int $pacienteId,
        public readonly ?int $especialidadeId,
        public readonly ?string $especialidadeNome,
        public readonly ?int $profissionalId,
        public readonly CarbonImmutable $data,
        public readonly ?string $horaInicio,
        public readonly ?int $lancamentoId = null,
        public readonly ?int $guiaId = null,
        public readonly ?string $guiaNumero = null,
    ) {
    }

    /**
     * Sessão já gravada, lida do banco para servir de parede contra as
     * candidatas. Exige `guia` e `guia.especialidade` carregadas.
     */
    public static function deLancamento(Lancamento $lancamento): self
    {
        return new self(
            referencia: 'lancamento:'.$lancamento->id,
            pacienteId: (int) $lancamento->guia->paciente_id,
            especialidadeId: $lancamento->guia->especialidade_id !== null ? (int) $lancamento->guia->especialidade_id : null,
            especialidadeNome: $lancamento->guia->especialidade?->nome,
            profissionalId: $lancamento->profissional_id !== null ? (int) $lancamento->profissional_id : null,
            data: CarbonImmutable::parse($lancamento->data_sessao),
            horaInicio: self::normalizarHora($lancamento->hora_inicio),
            lancamentoId: (int) $lancamento->id,
            guiaId: (int) $lancamento->guia_id,
            guiaNumero: $lancamento->guia->numero_guia,
        );
    }

    /**
     * Instante de início da sessão. É por ele que o intervalo mínimo é medido
     * — a regra fala de início contra início, não de fim contra início.
     */
    public function inicio(): ?CarbonImmutable
    {
        if ($this->horaInicio === null) {
            return null;
        }

        [$hora, $minuto] = array_pad(explode(':', $this->horaInicio), 2, '0');

        return $this->data->setTime((int) $hora, (int) $minuto);
    }

    public function dataString(): string
    {
        return $this->data->toDateString();
    }

    public function ehAba(): bool
    {
        return EspecialidadeAba::ehAba($this->especialidadeNome);
    }

    /**
     * Como a sessão aparece numa mensagem de conflito. Sem isso o operador lê
     * "conflito com outra sessão" e não sabe contra o quê bateu.
     */
    public function descricao(): string
    {
        $quando = $this->data->format('d/m/Y');

        if ($this->horaInicio !== null) {
            $quando .= ' às '.$this->horaInicio;
        }

        if ($this->guiaNumero !== null && $this->guiaNumero !== '') {
            return $quando.' (guia '.$this->guiaNumero.')';
        }

        return $quando;
    }

    /** Aceita 'HH:MM', 'HH:MM:SS' e o que vier do banco como objeto de hora. */
    public static function normalizarHora(mixed $hora): ?string
    {
        if ($hora === null) {
            return null;
        }

        $texto = trim((string) $hora);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/(\d{1,2}):(\d{2})/', $texto, $captura) !== 1) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $captura[1], (int) $captura[2]);
    }
}
