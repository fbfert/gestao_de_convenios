<?php

namespace App\Services;

use App\Exceptions\TabelaValorNaoEncontradaException;
use App\Models\Guia;
use App\Models\TabelaValor;
use App\Models\Convenio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TabelaValoresService
{
    public function criar(Convenio $convenio, array $dados): TabelaValor
    {
        return DB::transaction(function () use ($convenio, $dados) {
            $inicio = Carbon::parse($dados['vigente_desde'])->startOfDay();
            TabelaValor::query()->where('convenio_id', $convenio->id)
                ->where('especialidade_id', $dados['especialidade_id'] ?? null)
                ->where('profissional_id', $dados['profissional_id'] ?? null)
                ->whereNull('vigente_ate')
                ->update(['vigente_ate' => $inicio->copy()->subDay()->toDateString()]);
            return TabelaValor::query()->create([...$dados, 'tenant_id' => $convenio->tenant_id, 'convenio_id' => $convenio->id, 'vigente_desde' => $inicio->toDateString()]);
        });
    }

    public function encerrar(TabelaValor $valor, ?string $data): TabelaValor
    {
        $valor->update(['vigente_ate' => Carbon::parse($data ?? today())->toDateString()]);
        return $valor;
    }
    public function obterValorVigente(Guia $guia, ?int $profissionalId = null): string
    {
        $profissionalId ??= $guia->profissional_id;
        $hoje = today();

        $candidatos = [
            [
                'convenio_id' => $guia->convenio_id,
                'especialidade_id' => $guia->especialidade_id,
                'profissional_id' => $profissionalId,
            ],
            [
                'convenio_id' => $guia->convenio_id,
                'especialidade_id' => $guia->especialidade_id,
                'profissional_id' => null,
            ],
            [
                'convenio_id' => $guia->convenio_id,
                'especialidade_id' => null,
                'profissional_id' => null,
            ],
        ];

        foreach ($candidatos as $filtro) {
            $tabelaValor = TabelaValor::query()
                ->where('convenio_id', $filtro['convenio_id'])
                ->where('vigente_desde', '<=', $hoje)
                ->where(function ($query) use ($hoje) {
                    $query->whereNull('vigente_ate')
                        ->orWhere('vigente_ate', '>=', $hoje);
                })
                ->where(function ($query) use ($filtro) {
                    if ($filtro['especialidade_id'] === null) {
                        $query->whereNull('especialidade_id');
                    } else {
                        $query->where('especialidade_id', $filtro['especialidade_id']);
                    }

                    if ($filtro['profissional_id'] === null) {
                        $query->whereNull('profissional_id');
                    } else {
                        $query->where('profissional_id', $filtro['profissional_id']);
                    }
                })
                ->orderByDesc('vigente_desde')
                ->first();

            if ($tabelaValor) {
                return number_format((float) $tabelaValor->valor, 2, '.', '');
            }
        }

        throw TabelaValorNaoEncontradaException::forGuia(
            (int) $guia->convenio_id,
            (int) $guia->especialidade_id,
            $profissionalId ? (int) $profissionalId : null
        );
    }
}
