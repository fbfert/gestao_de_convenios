<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'openai.ativo' => ['required', 'boolean'],
            'prompts' => ['present', 'array'],
            'prompts.*.chave' => [
                'required',
                'string',
                Rule::in(['ler_solicitacao_medica', 'ler_sessoes_escaneadas']),
            ],
            'prompts.*.nome' => ['required', 'string', 'max:255'],
            'prompts.*.descricao' => ['nullable', 'string'],
            'prompts.*.model_id' => ['nullable', 'string', 'max:255'],
            'prompts.*.system_prompt' => ['required', 'string'],
            'prompts.*.user_prompt' => ['required', 'string'],
            'prompts.*.ativo' => ['required', 'boolean'],
        ];
    }
}
