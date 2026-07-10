<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LancamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'antecipacao_id' => $this->antecipacao_id,
            'profissional_id' => $this->profissional_id,
            'data_sessao' => $this->data_sessao?->toDateString(),
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
