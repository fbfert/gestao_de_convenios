<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioProfissionalMapeamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'profissional_id' => $this->profissional_id,
            'codigo_operadora' => $this->codigo_operadora,
            'nome_operadora' => $this->nome_operadora,
            'ativo' => $this->ativo,
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
            ]),
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
            ]),
        ];
    }
}
