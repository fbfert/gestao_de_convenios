<?php

namespace App\Services;

use App\Exceptions\TabelaValorNaoEncontradaException;
use App\Models\Guia;
use App\Models\TabelaValor;

class TabelaValoresService
{
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
