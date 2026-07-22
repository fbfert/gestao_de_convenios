<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $smtp = $this->resource['smtp'];

        return [
            'smtp' => $smtp ? [
                'id' => $smtp->id,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'username' => $smtp->username,
                'encryption' => $smtp->encryption,
                'from_email' => $smtp->from_email,
                'from_name' => $smtp->from_name,
                'ativo' => $smtp->ativo,
                'senha_configurada' => filled($smtp->password),
            ] : null,
            'templates' => $this->resource['templates']->map(fn ($template) => [
                'id' => $template->id,
                'chave' => $template->chave,
                'nome' => $template->nome,
                'assunto' => $template->assunto,
                'corpo' => $template->corpo,
                'ativo' => $template->ativo,
            ])->values(),
        ];
    }
}
