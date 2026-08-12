<?php

namespace App\Http\Requests;

use App\Models\AiPromptTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAiPromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prompt = $this->route('aiPromptTemplate');

        return [
            'chave' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('ai_prompt_templates', 'chave')
                    ->where('tenant_id', (int) $this->user()->tenant_id)
                    ->ignore($prompt?->getKey()),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['required', 'string'],
            'user_prompt' => ['required', 'string'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var AiPromptTemplate|null $prompt */
            $prompt = $this->route('aiPromptTemplate');

            if (! $prompt?->ehDeSistema()) {
                return;
            }

            // O conteudo do prompt de sistema e livre; so a chave e travada,
            // porque e por ela que o servico encontra o registro.
            if ($this->input('chave') !== $prompt->chave) {
                $validator->errors()->add(
                    'chave',
                    'A chave deste prompt é usada pelo sistema e não pode ser alterada.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'chave.regex' => 'A chave aceita apenas letras minúsculas, números e underscore, começando por letra.',
            'chave.unique' => 'Já existe um prompt com esta chave.',
        ];
    }
}
