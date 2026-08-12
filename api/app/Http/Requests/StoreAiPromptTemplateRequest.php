<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiPromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A chave e o identificador que o codigo usa para achar o prompt,
            // entao segue o formato de slug e e unica dentro do tenant. O
            // unique precisa do filtro por tenant: chaves iguais em tenants
            // diferentes sao legitimas e o indice do banco e composto.
            'chave' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('ai_prompt_templates', 'chave')
                    ->where('tenant_id', (int) $this->user()->tenant_id),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['required', 'string'],
            'user_prompt' => ['required', 'string'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'chave.regex' => 'A chave aceita apenas letras minúsculas, números e underscore, começando por letra.',
            'chave.unique' => 'Já existe um prompt com esta chave.',
        ];
    }
}
