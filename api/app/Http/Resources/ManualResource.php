<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tipo' => $this->tipo,
            'conteudo_html' => $this->conteudo_html,
            'atualizado_em' => $this->updated_at?->toIso8601String(),
            'atualizado_por' => $this->atualizadoPor?->name,
        ];
    }
}
