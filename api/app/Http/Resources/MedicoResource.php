<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'crm' => $this->crm,
            'crm_uf' => $this->crm_uf,
            'especialidade_medica' => $this->especialidade_medica,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'ativo' => $this->ativo,
        ];
    }
}
