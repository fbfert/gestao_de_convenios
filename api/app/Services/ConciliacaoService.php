<?php

namespace App\Services;

use App\Exceptions\ConciliacaoStatusInvalidoException;
use App\Models\ConciliacaoFinanceira;
use App\Models\Guia;
use App\Models\Lancamento;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ConciliacaoService
{
    public function __construct(
        private readonly TabelaValoresService $tabelaValoresService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return ConciliacaoFinanceira::query()
            ->with(['guia', 'profissional'])
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('convenio_id', $convenioId)))
            ->when(Arr::get($filtros, 'especialidade_id'), fn ($query, $especialidadeId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('especialidade_id', $especialidadeId)))
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function gerarParaGuia(Guia $guia): ConciliacaoFinanceira
    {
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
}
