<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AntecipacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guia_id' => $this->guia_id,
            'paciente_id' => $this->paciente_id,
            'convenio_id' => $this->convenio_id,
            'ciclo_inicio' => $this->ciclo_inicio?->toDateString(),
            'ciclo_fim' => $this->ciclo_fim?->toDateString(),
            'qtd_autorizada' => $this->qtd_autorizada,
            'qtd_utilizada' => $this->qtd_utilizada,
            'status' => $this->status,
            'lancamentos' => $this->whenLoaded('lancamentos', fn () => $this->lancamentos->map(fn ($lancamento) => [
                'id' => $lancamento->id,
                'data_sessao' => $lancamento->data_sessao?->toDateString(),
                'hora_inicio' => $lancamento->hora_inicio,
                'hora_fim' => $lancamento->hora_fim,
                'status' => $lancamento->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
