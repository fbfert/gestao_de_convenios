<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LancamentoService
{
    use AppliesOwnScope;

    public function __construct(
        private readonly AntecipacaoService $antecipacaoService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Lancamento::query()->with(['antecipacao', 'profissional']),
            'lancamentos.view',
            'lancamentos.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'data_sessao'), fn ($query, $dataSessao) => $query->whereDate('data_sessao', $dataSessao))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function buscar(int $id): Lancamento
    {
        return Lancamento::query()->with(['antecipacao', 'profissional'])->findOrFail($id);
    }

    public function registrar(Antecipacao $antecipacao, Profissional $profissional, Carbon $data): Lancamento
    {
        return DB::transaction(function () use ($antecipacao, $profissional, $data) {
            $lancamento = Lancamento::query()->create([
                'tenant_id' => $antecipacao->tenant_id,
                'antecipacao_id' => $antecipacao->id,
                'profissional_id' => $profissional->id,
                'data_sessao' => $data->toDateString(),
                'status' => 'completed',
                'observacoes' => null,
            ]);

            $this->antecipacaoService->consumirCota($antecipacao);

            return $lancamento->refresh();
        });
    }
}
