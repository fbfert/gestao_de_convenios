<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'connector_type' => ['required', Rule::in(['manual', 'api', 'scraping'])],
            'connector_driver' => ['nullable', Rule::in(['unimed_rda'])],

            // Formato da carteirinha: lista de tamanhos de bloco. Ausente ou
            // vazia significa texto livre. Os limites sao de sanidade — evitam
            // que um erro de digitacao gere dezenas de caixinhas na tela.
            'carteirinha_blocos' => ['nullable', 'array', 'max:8'],
            'carteirinha_blocos.*' => ['integer', 'min:1', 'max:12'],

            'ativo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Lista vazia e null significam a mesma coisa: sem formato definido.
        // Normalizar aqui evita gravar `[]`, que viraria um caso especial em
        // toda leitura.
        if ($this->input('carteirinha_blocos') === []) {
            $this->merge(['carteirinha_blocos' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'carteirinha_blocos.max' => 'A carteirinha aceita no máximo 8 blocos.',
            'carteirinha_blocos.*.min' => 'Cada bloco precisa ter ao menos 1 dígito.',
            'carteirinha_blocos.*.max' => 'Cada bloco aceita no máximo 12 dígitos.',
        ];
    }
}
