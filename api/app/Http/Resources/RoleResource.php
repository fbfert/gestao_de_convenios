<?php

namespace App\Http\Resources;

use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // A tela usa isto para não oferecer renomear e excluir. A recusa
            // de verdade acontece no RoleController; aqui é só para o botão
            // não prometer o que a API vai negar.
            'sistema' => RoleCatalog::ehDeSistema($this->name),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
