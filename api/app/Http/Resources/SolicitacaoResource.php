<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'profissional_id' => $this->profissional_id,
            'especialidade_id' => $this->especialidade_id,
            'convenio_id' => $this->convenio_id,
            'medico_id' => $this->medico_id,
            'medico' => $this->whenLoaded('medico', fn () => [
                'id' => $this->medico->id,
                'nome' => $this->medico->nome,
                'crm' => $this->medico->crm,
                'especialidade_medica' => $this->medico->especialidade_medica,
            ]),
            'status' => $this->status,
            'solicitado_em' => $this->solicitado_em?->toDateString(),
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
