<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $openai = $this->resource['openai'];

        return [
            'openai' => $openai ? [
                'id' => $openai->id,
                'base_url' => $openai->base_url,
                'organization_id' => $openai->organization_id,
                'project_id' => $openai->project_id,
                'model_id' => $openai->model_id,
                'ativo' => $openai->ativo,
                'api_key_configurada' => filled($openai->api_key),
            ] : null,
            'prompts' => $this->resource['prompts']->map(fn ($prompt) => [
                'id' => $prompt->id,
                'chave' => $prompt->chave,
                'nome' => $prompt->nome,
                'descricao' => $prompt->descricao,
                'model_id' => $prompt->model_id,
                'system_prompt' => $prompt->system_prompt,
                'user_prompt' => $prompt->user_prompt,
                'ativo' => $prompt->ativo,
            ])->values(),
        ];
    }
}
