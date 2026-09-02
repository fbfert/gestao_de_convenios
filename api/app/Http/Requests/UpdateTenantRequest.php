<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Editável a pedido do usuário (02/09/2026). O slug não é usado
            // para resolver o tenant em runtime (login/middleware usam
            // tenant_id) — só aparece como identificador de exibição. O único
            // risco real são os seeders de demo/dev que buscam por
            // `where('slug', 'clinica-exemplo')`; renomear esse tenant
            // específico quebraria essas buscas, mas não afeta produção.
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('tenants', 'slug')->ignore($this->route('tenant')),
            ],
            'cnpj' => ['nullable', 'string', 'max:32'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O identificador aceita apenas letras minúsculas, números e hífen, começando por letra.',
            'slug.unique' => 'Já existe uma clínica com este identificador.',
        ];
    }
}
