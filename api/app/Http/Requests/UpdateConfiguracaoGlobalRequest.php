<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracaoGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 0 desliga a expiração. O teto de 43200 são 30 dias — acima disso
            // a configuração deixa de ser prazo e vira "nunca" mal escrito.
            'sessao_minutos' => ['required', 'integer', 'min:0', 'max:43200'],
            'senha_alerta_dias' => ['required', 'integer', 'min:1', 'max:180'],
            'sessoes_padrao' => ['required', 'integer', 'min:1', 'max:999'],
            'itens_por_pagina' => ['required', 'integer', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'sessao_minutos.max' => 'O tempo de sessão não pode passar de 43200 minutos (30 dias).',
            'itens_por_pagina.min' => 'A listagem precisa mostrar ao menos 5 itens por página.',
        ];
    }
}
