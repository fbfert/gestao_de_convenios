<?php

namespace App\Services\Relatorios;

use App\Http\Requests\RelatorioFiltrosRequest;

/**
 * O recorte pedido: período, clínica e os filtros de convênio, especialidade e
 * profissional.
 *
 * Imutável, e montado uma vez por requisição: é este objeto que produz a chave
 * de cache. Se qualquer parte dele pudesse mudar depois, duas consultas
 * diferentes acabariam gravando e lendo a mesma chave — o relatório de uma
 * clínica respondendo pela de outra é exatamente a falha que o cache por tenant
 * existe para impedir.
 *
 * `tenantId` nulo significa TODAS as clínicas, e só o super admin chega aqui
 * assim (ver RelatorioFiltrosRequest). Nulo como "todas" é deliberado: obriga
 * quem consome a decidir o que fazer nesse caso, em vez de deixar um valor
 * padrão silencioso somando clínica alheia.
 */
final class RelatorioFiltros
{
    public function __construct(
        public readonly RelatorioPeriodo $periodo,
        public readonly ?int $tenantId,
        public readonly ?int $convenioId = null,
        public readonly ?int $especialidadeId = null,
        public readonly ?int $profissionalId = null,
        public readonly bool $comparar = false,
    ) {}

    public static function doRequest(RelatorioFiltrosRequest $request): self
    {
        return new self(
            periodo: $request->periodo(),
            tenantId: $request->tenantIdDoRelatorio(),
            convenioId: $request->filled('convenio_id') ? (int) $request->input('convenio_id') : null,
            especialidadeId: $request->filled('especialidade_id') ? (int) $request->input('especialidade_id') : null,
            profissionalId: $request->filled('profissional_id') ? (int) $request->input('profissional_id') : null,
            comparar: $request->boolean('comparar'),
        );
    }

    public function todasAsClinicas(): bool
    {
        return $this->tenantId === null;
    }

    /** Como a chave de cache e a resposta nomeiam a clínica. */
    public function escopoDaClinica(): string
    {
        return $this->tenantId === null ? 'todos' : (string) $this->tenantId;
    }

    /** O mesmo recorte no período anterior — usado para o valor de comparação dos KPIs. */
    public function noPeriodoAnterior(): self
    {
        return new self(
            periodo: $this->periodo->anterior(),
            tenantId: $this->tenantId,
            convenioId: $this->convenioId,
            especialidadeId: $this->especialidadeId,
            profissionalId: $this->profissionalId,
            comparar: false,
        );
    }

    /**
     * Identidade do recorte para a chave de cache.
     *
     * Inclui `comparar` porque a resposta com comparação tem campo a mais: sem
     * isso, quem pedisse sem comparar primeiro deixaria no cache uma resposta
     * sem `anterior`, e o próximo com comparação a receberia de volta assim.
     */
    public function hash(): string
    {
        return sha1(json_encode([
            'de' => $this->periodo->de->toDateString(),
            'ate' => $this->periodo->ate->toDateString(),
            'granularidade' => $this->periodo->granularidade,
            'convenio' => $this->convenioId,
            'especialidade' => $this->especialidadeId,
            'profissional' => $this->profissionalId,
            'comparar' => $this->comparar,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, int> filtros pedidos, sem os que vieram vazios */
    public function informados(): array
    {
        return array_filter([
            'convenio_id' => $this->convenioId,
            'especialidade_id' => $this->especialidadeId,
            'profissional_id' => $this->profissionalId,
        ], fn (?int $valor) => $valor !== null);
    }
}
