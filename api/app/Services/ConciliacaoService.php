<?php

namespace App\Services;

use App\Exceptions\ConciliacaoStatusInvalidoException;
use App\Models\ConciliacaoFinanceira;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ConciliacaoService
{
    use AppliesOwnScope;

    private const PERCENTUAL_REPASSE_PADRAO = '64.00';

    public function __construct(
        private readonly TabelaValoresService $tabelaValoresService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            ConciliacaoFinanceira::query()->with(['guia', 'profissional']),
            'conciliacoes.view',
            'conciliacoes.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('convenio_id', $convenioId)))
            ->when(Arr::get($filtros, 'especialidade_id'), fn ($query, $especialidadeId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('especialidade_id', $especialidadeId)))
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function gerarParaGuia(Guia $guia): ConciliacaoFinanceira
    {
        $guia->loadMissing('profissional');

        $quantidade = Lancamento::query()
            ->where('status', 'completed')
            ->whereHas('antecipacao', function ($query) use ($guia) {
                $query->where('guia_id', $guia->id);
            })
            ->count();

        $valorUnitario = $this->tabelaValoresService->obterValorVigente($guia, $guia->profissional_id);
        $valorTotal = number_format($quantidade * (float) $valorUnitario, 2, '.', '');
        return ConciliacaoFinanceira::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal,
            'referencia_analitico_convenio' => null,
            'status' => 'pending',
            'conferido_em' => null,
        ]);
    }

    /**
     * @return array{percentual_repasse_profissional: string, percentual_retencao_clinica: string, valor_repasse_unitario: string, valor_repasse_total: string, valor_retencao_unitario: string, valor_retencao_total: string}
     */
    public function calcularRepasse(Guia $guia, ?int $profissionalId = null, ?int $quantidade = null): array
    {
        $profissional = $profissionalId
            ? Profissional::query()->findOrFail($profissionalId)
            : $guia->profissional()->firstOrFail();

        $valorUnitario = $this->tabelaValoresService->obterValorVigente($guia, $profissional->id);
        $percentualRepasse = $this->percentualRepasseProfissional($profissional);
        $percentualRetencao = number_format(100 - (float) $percentualRepasse, 2, '.', '');
        $quantidade ??= 1;
        $valorRepasseUnitario = $this->calcularPercentual($valorUnitario, $percentualRepasse);
        $valorRetencaoUnitario = $this->calcularPercentual($valorUnitario, $percentualRetencao);

        return [
            'percentual_repasse_profissional' => $percentualRepasse,
            'percentual_retencao_clinica' => $percentualRetencao,
            'valor_repasse_unitario' => $valorRepasseUnitario,
            'valor_repasse_total' => number_format((float) $valorRepasseUnitario * $quantidade, 2, '.', ''),
            'valor_retencao_unitario' => $valorRetencaoUnitario,
            'valor_retencao_total' => number_format((float) $valorRetencaoUnitario * $quantidade, 2, '.', ''),
        ];
    }

    public function marcarConferido(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceira
    {
        if ($conciliacao->status !== 'pending') {
            throw ConciliacaoStatusInvalidoException::transicaoInvalida($conciliacao->status, 'reviewed');
        }

        $conciliacao->status = 'reviewed';
        $conciliacao->conferido_em = Carbon::now();
        $conciliacao->save();

        return $conciliacao->refresh();
    }

    public function marcarPago(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceira
    {
        if ($conciliacao->status !== 'reviewed') {
            throw ConciliacaoStatusInvalidoException::transicaoInvalida($conciliacao->status, 'paid');
        }

        $conciliacao->status = 'paid';
        $conciliacao->save();

        return $conciliacao->refresh();
    }

    private function percentualRepasseProfissional(?Profissional $profissional): string
    {
        return number_format((float) ($profissional?->percentual_repasse ?? self::PERCENTUAL_REPASSE_PADRAO), 2, '.', '');
    }

    private function calcularPercentual(string $valor, string $percentual): string
    {
        return number_format(((float) $valor * (float) $percentual) / 100, 2, '.', '');
    }
}
