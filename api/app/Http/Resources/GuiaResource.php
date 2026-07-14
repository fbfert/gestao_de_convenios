<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitacao_id' => $this->solicitacao_id,
            'convenio_id' => $this->convenio_id,
            'paciente_id' => $this->paciente_id,
            'profissional_id' => $this->profissional_id,
            'especialidade_id' => $this->especialidade_id,
            'numero_guia' => $this->numero_guia,
            'tipo_terapia' => $this->tipo_terapia,
            'status' => $this->status,
            'data_solicitacao' => $this->data_solicitacao?->toDateString(),
            'data_finalizacao' => $this->data_finalizacao?->toDateString(),
            'senha' => $this->senha,
            'validade_senha' => $this->validade_senha?->toDateString(),
            'observacoes' => $this->observacoes,
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente->id,
                'nome' => $this->paciente->nome,
                'carteirinha' => $this->paciente->carteirinha,
            ]),
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
            ]),
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
            ]),
            'especialidade' => $this->whenLoaded('especialidade', fn () => [
                'id' => $this->especialidade->id,
                'nome' => $this->especialidade->nome,
            ]),
            'antecipacoes' => $this->whenLoaded('antecipacoes', fn () => $this->antecipacoes->map(fn ($antecipacao) => [
                'id' => $antecipacao->id,
                'qtd_autorizada' => $antecipacao->qtd_autorizada,
                'qtd_utilizada' => $antecipacao->qtd_utilizada,
                'status' => $antecipacao->status,
            ])->values()),
            'conciliacoes' => $this->whenLoaded('conciliacoes', fn () => $this->conciliacoes->map(fn ($conciliacao) => [
                'id' => $conciliacao->id,
                'status' => $conciliacao->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
