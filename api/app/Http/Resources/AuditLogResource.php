<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acao' => $this->acao,
            'entidade' => $this->entidade,
            'entidade_id' => $this->entidade_id,
            'usuario' => $this->user?->name,
            'usuario_id' => $this->user_id,
            'payload' => $this->payload,
            // Só os eventos de acesso preenchem estes dois.
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
