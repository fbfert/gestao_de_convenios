<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'carteirinha' => $this->carteirinha,
            'convenio_id' => $this->convenio_id,
            'telefone' => $this->telefone,
            'clinica_agil_id' => $this->clinica_agil_id,
            'ativo' => $this->ativo,
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
                'connector_driver' => $this->convenio->connector_driver,
                // A tela agrupa a carteirinha por estes blocos ao exibir.
                'carteirinha_blocos' => $this->convenio->blocosCarteirinha(),
            ]),
        ];
    }
}
