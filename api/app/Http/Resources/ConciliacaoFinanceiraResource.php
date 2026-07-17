<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConciliacaoFinanceiraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'status' => $this->status,
            'conferido_em' => $this->conferido_em?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
