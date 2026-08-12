<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'connector_type' => $this->connector_type,
            'connector_driver' => $this->connector_driver,
            'carteirinha_blocos' => $this->blocosCarteirinha(),
            'ativo' => $this->ativo,
        ];
    }
}
