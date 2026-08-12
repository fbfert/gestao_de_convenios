<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'openai' => ['required', 'array'],
            'openai.api_key' => ['nullable', 'string', 'max:2000'],
            'openai.base_url' => ['required', 'url', 'max:255'],
            'openai.organization_id' => ['nullable', 'string', 'max:255'],
            'openai.project_id' => ['nullable', 'string', 'max:255'],
            'openai.model_id' => ['nullable', 'string', 'max:255'],
            'openai.ativo' => ['required', 'boolean'],
            // `prompts` saiu daqui: passaram a ter CRUD proprio em
            // /configuracoes/ia/prompts, com chave livre por tenant. Manter a
            // lista neste PUT exigiria repetir as regras e a trava das chaves
            // de sistema em dois lugares.
        ];
    }
}
