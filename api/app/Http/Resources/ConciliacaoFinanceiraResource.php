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
            'status' => $this->status,
            'conferido_em' => $this->conferido_em?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
