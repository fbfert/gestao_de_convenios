<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'ativo' => $this->ativo,
            'role' => $this->roles->first()?->name ?? $this->getRoleNames()->first(),
            'profissional_id' => $this->profissional_id,
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
                'especialidade_id' => $this->profissional->especialidade_id,
            ]),
        ];
    }
}
