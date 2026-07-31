<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnimedSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $credential = $this->resource['credential'];
        $convenios = $this->resource['convenios'];

        return [
            'credential' => $credential ? [
                'id' => $credential->id,
                'login' => $credential->login,
                'base_url' => $credential->base_url,
                'ativo' => $credential->ativo,
                'senha_configurada' => filled($credential->password),
                'automation_paused_at' => $credential->automation_paused_at?->toISOString(),
                'automation_paused_reason' => $credential->automation_paused_reason,
            ] : null,
            'convenio_id' => $this->resource['convenio_id'],
            'convenios' => $convenios->map(fn ($convenio) => [
                'id' => $convenio->id,
                'nome' => $convenio->nome,
                'connector_type' => $convenio->connector_type,
                'connector_driver' => $convenio->connector_driver,
                'ativo' => $convenio->ativo,
            ])->values(),
        ];
    }
}
