<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chave' => $this->chave,
            'nome' => $this->nome,
            'assunto' => $this->assunto,
            'corpo' => $this->corpo,
            'ativo' => $this->ativo,
        ];
    }
}
