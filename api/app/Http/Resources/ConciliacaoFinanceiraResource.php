<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConciliacaoFinanceiraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movimentos = $this->relationLoaded('movimentosFinanceiros')
            ? $this->movimentosFinanceiros->map(fn ($movimento) => [
                'id' => $movimento->id,
                'tipo' => $movimento->tipo,
                'origem' => $movimento->origem,
                'quantidade' => $movimento->quantidade,
                'valor_unitario' => $movimento->valor_unitario,
                'valor_total' => $movimento->valor_total,
                'referencia_analitico_convenio' => $movimento->referencia_analitico_convenio,
                'descricao' => $movimento->descricao,
                'profissional_informado' => $movimento->profissionalInformado ? [
                    'id' => $movimento->profissionalInformado->id,
                    'nome' => $movimento->profissionalInformado->nome,
                ] : null,
                'profissional_executor' => $movimento->profissionalExecutor ? [
                    'id' => $movimento->profissionalExecutor->id,
                    'nome' => $movimento->profissionalExecutor->nome,
                ] : null,
                'created_at' => $movimento->created_at?->toISOString(),
                'updated_at' => $movimento->updated_at?->toISOString(),
            ])
            : collect();

        $entradaTotal = collect($movimentos)
            ->where('tipo', 'entrada')
            ->sum(fn ($movimento) => (float) $movimento['valor_total']);
        $saidaTotal = collect($movimentos)
            ->where('tipo', 'saida')
            ->sum(fn ($movimento) => (float) $movimento['valor_total']);

        return [
            'id' => $this->id,
            'guia_id' => $this->guia_id,
            'profissional_id' => $this->profissional_id,
            'especialidade_id' => $this->guia?->especialidade_id,
            'convenio_id' => $this->guia?->convenio_id,
            'quantidade' => $this->quantidade,
            'valor_unitario' => $this->valor_unitario,
            'valor_total' => $this->valor_total,
            'percentual_repasse_profissional' => $this->percentual_repasse_profissional,
            'percentual_retencao_clinica' => $this->percentual_retencao_clinica,
            'valor_repasse_unitario' => $this->valor_repasse_unitario,
            'valor_repasse_total' => $this->valor_repasse_total,
            'valor_retencao_unitario' => $this->valor_retencao_unitario,
            'valor_retencao_total' => $this->valor_retencao_total,
            'entrada_total' => number_format($entradaTotal, 2, '.', ''),
            'saida_total' => number_format($saidaTotal, 2, '.', ''),
            'saldo_total' => number_format($entradaTotal - $saidaTotal, 2, '.', ''),
            'profissional_informado' => $this->relationLoaded('guia') ? [
                'id' => $this->guia?->profissional?->id,
                'nome' => $this->guia?->profissional?->nome,
            ] : null,
            'movimentos_financeiros' => $movimentos,
            'status' => $this->status,
            'conferido_em' => $this->conferido_em?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
