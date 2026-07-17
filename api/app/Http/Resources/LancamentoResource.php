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
            'hora_inicio' => $this->hora_inicio,
            'hora_fim' => $this->hora_fim,
            'acompanhante' => $this->acompanhante,
            'resumo_atividades' => $this->resumo_atividades,
            'transcricao_bruta' => $this->transcricao_bruta,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'antecipacao' => $this->whenLoaded('antecipacao', fn () => [
                'id' => $this->antecipacao->id,
                'guia_id' => $this->antecipacao->guia_id,
            ]),
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
