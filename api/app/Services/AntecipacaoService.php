<?php

namespace App\Services;

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Exceptions\ConvenioRegraNaoEncontradaException;
use App\Models\Antecipacao;
use App\Models\ConvenioRegra;
use App\Models\Guia;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class AntecipacaoService
{
    use AppliesOwnScope;

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Antecipacao::query()->with(['guia', 'paciente', 'convenio', 'lancamentos']),
            'antecipacoes.view',
            'antecipacoes.viewOwn',
            fn ($query, $user) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('profissional_id', $user->profissional_id))
        );

        return $query
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'paciente_id'), fn ($query, $pacienteId) => $query->where('paciente_id', $pacienteId))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function buscar(int $id): Antecipacao
    {
        return Antecipacao::query()->with(['guia', 'paciente', 'convenio', 'lancamentos'])->findOrFail($id);
    }

    public function abrirCiclo(Guia $guia): Antecipacao
    {
        $regra = ConvenioRegra::query()
            ->where('convenio_id', $guia->convenio_id)
            ->where('tipo_terapia', $guia->tipo_terapia)
            ->where('vigente_desde', '<=', today())
            ->where(function ($query) {
                $query->whereNull('vigente_ate')
                    ->orWhere('vigente_ate', '>=', today());
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if (! $regra) {
            throw ConvenioRegraNaoEncontradaException::forGuia(
                (int) $guia->convenio_id,
                (string) $guia->tipo_terapia
            );
        }

        $cicloInicio = today();
        $cicloFim = $this->calcularCicloFim($cicloInicio, $regra->frequencia_lancamento);

        return Antecipacao::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'paciente_id' => $guia->paciente_id,
            'convenio_id' => $guia->convenio_id,
            'ciclo_inicio' => $cicloInicio,
            'ciclo_fim' => $cicloFim,
            'qtd_autorizada' => $regra->qtd_autorizada_por_ciclo,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ]);
    }

    public function consumirCota(Antecipacao $antecipacao): void
    {
        if ($antecipacao->status === 'closed' || $antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada) {
            throw AntecipacaoCotaEsgotadaException::forAntecipacao((int) $antecipacao->id);
        }

        $antecipacao->qtd_utilizada++;

        if ($antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada) {
            $antecipacao->status = 'closed';
        }

        $antecipacao->save();
    }

    private function calcularCicloFim(Carbon $inicio, string $frequencia): Carbon
    {
        return match ($frequencia) {
            'diaria' => $inicio->copy(),
            'semanal' => $inicio->copy()->addDays(6),
            'mensal' => $inicio->copy()->addMonthNoOverflow()->subDay(),
            default => $inicio->copy(),
        };
    }
}
