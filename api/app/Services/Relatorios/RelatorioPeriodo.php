<?php

namespace App\Services\Relatorios;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * O período de um relatório: início, fim, granularidade e o período anterior.
 *
 * Tudo em America/Sao_Paulo, sempre — e não no fuso do processo. A API recebe
 * `de`/`ate` como data pura (`2026-09-01`), sem hora e sem deslocamento; quem
 * interpretar isso no fuso do servidor faz o primeiro e o último dia do
 * intervalo pegarem três horas do dia vizinho. O relatório passaria a contar,
 * em silêncio, sessões da madrugada seguinte no mês errado.
 *
 * Imutável de propósito: o período entra na chave de cache e é comparado com o
 * anterior na mesma requisição. Um objeto que alguém pudesse empurrar um dia
 * para frente faria as duas coisas divergirem sem erro nenhum.
 */
final class RelatorioPeriodo
{
    public const FUSO = 'America/Sao_Paulo';

    /**
     * Um ano e um dia (o dia a mais cobre o ano bissexto).
     *
     * O teto existe por dois motivos: a resposta vai para o cache, e uma
     * consulta sem limite guardaria série arbitrariamente grande; e a query
     * agregada precisa ter um custo máximo conhecido antes de chegar em
     * produção, não depois.
     */
    public const MAX_DIAS = 366;

    public const DIA = 'dia';

    public const SEMANA = 'semana';

    public const MES = 'mes';

    public const GRANULARIDADES = [self::DIA, self::SEMANA, self::MES];

    /** Acima deste tamanho a série diária vira ruído: 365 pontos num gráfico de 600px. */
    private const LIMITE_DIA = 31;

    private const LIMITE_SEMANA = 120;

    public const PRESET_HOJE = 'hoje';

    public const PRESET_7_DIAS = '7dias';

    public const PRESET_30_DIAS = '30dias';

    public const PRESET_MES_ATUAL = 'mes_atual';

    public const PRESET_MES_ANTERIOR = 'mes_anterior';

    public const PRESETS = [
        self::PRESET_HOJE,
        self::PRESET_7_DIAS,
        self::PRESET_30_DIAS,
        self::PRESET_MES_ATUAL,
        self::PRESET_MES_ANTERIOR,
    ];

    private function __construct(
        public readonly CarbonImmutable $de,
        public readonly CarbonImmutable $ate,
        public readonly string $granularidade,
    ) {}

    /**
     * @param  string|DateTimeInterface  $de  data pura; a hora, se vier, é descartada
     * @param  string|null  $granularidade  null = escolhida pelo tamanho do período
     */
    public static function entre(
        string|DateTimeInterface $de,
        string|DateTimeInterface $ate,
        ?string $granularidade = null,
    ): self {
        $inicio = self::comoData($de);
        $fim = self::comoData($ate);

        if ($fim->lessThan($inicio)) {
            throw new InvalidArgumentException('A data final do período não pode ser anterior à inicial.');
        }

        $dias = $inicio->diffInDays($fim) + 1;

        if ($dias > self::MAX_DIAS) {
            throw new InvalidArgumentException('O período não pode ultrapassar '.self::MAX_DIAS.' dias.');
        }

        if ($granularidade !== null && ! in_array($granularidade, self::GRANULARIDADES, true)) {
            throw new InvalidArgumentException("Granularidade inválida: {$granularidade}.");
        }

        return new self($inicio, $fim, $granularidade ?? self::granularidadeAutomatica($dias));
    }

    public static function doPreset(string $preset, ?string $granularidade = null): self
    {
        $hoje = CarbonImmutable::now(self::FUSO)->startOfDay();

        [$de, $ate] = match ($preset) {
            self::PRESET_HOJE => [$hoje, $hoje],
            // Sete dias CONTANDO hoje, e não hoje mais sete: o preset diz "7
            // dias" e o usuário conta o de hoje entre eles.
            self::PRESET_7_DIAS => [$hoje->subDays(6), $hoje],
            self::PRESET_30_DIAS => [$hoje->subDays(29), $hoje],
            self::PRESET_MES_ATUAL => [$hoje->startOfMonth(), $hoje],
            self::PRESET_MES_ANTERIOR => [
                $hoje->subMonthNoOverflow()->startOfMonth(),
                $hoje->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            default => throw new InvalidArgumentException("Preset de período desconhecido: {$preset}."),
        };

        return self::entre($de, $ate, $granularidade);
    }

    public static function granularidadeAutomatica(int $dias): string
    {
        return match (true) {
            $dias <= self::LIMITE_DIA => self::DIA,
            $dias <= self::LIMITE_SEMANA => self::SEMANA,
            default => self::MES,
        };
    }

    public function dias(): int
    {
        return $this->de->diffInDays($this->ate) + 1;
    }

    /**
     * O período imediatamente anterior, de mesmo tamanho, terminando na véspera.
     *
     * Mesmo tamanho em DIAS, e não "o mês anterior": comparar 30 dias com 28 faz
     * a variação percentual mentir por 7% sem que nada na tela avise. Quem quer
     * mês contra mês escolhe o preset de mês, e aí os dois recortes já casam.
     *
     * A granularidade é herdada, não recalculada — os dois períodos têm o mesmo
     * número de dias, e recalcular só abriria espaço para divergirem.
     */
    public function anterior(): self
    {
        $fim = $this->de->subDay();

        return new self($fim->subDays($this->dias() - 1), $fim, $this->granularidade);
    }

    /** Primeiro instante do período, para comparar com coluna `datetime`. */
    public function inicio(): CarbonImmutable
    {
        return $this->de->startOfDay();
    }

    /** Último instante do período — inclusivo, porque `ate` é um dia, não um corte. */
    public function fim(): CarbonImmutable
    {
        return $this->ate->endOfDay();
    }

    /** @return array{de: string, ate: string, granularidade: string, dias: int} */
    public function toArray(): array
    {
        return [
            'de' => $this->de->toDateString(),
            'ate' => $this->ate->toDateString(),
            'granularidade' => $this->granularidade,
            'dias' => $this->dias(),
        ];
    }

    private static function comoData(string|DateTimeInterface $valor): CarbonImmutable
    {
        return $valor instanceof DateTimeInterface
            ? CarbonImmutable::instance($valor)->setTimezone(self::FUSO)->startOfDay()
            // `parse` com fuso explícito, e não `setTimezone` depois: "2026-09-01"
            // sem fuso seria lido como meia-noite do servidor e, ao converter,
            // viraria 21h de 31/08.
            : CarbonImmutable::parse($valor, self::FUSO)->startOfDay();
    }
}
