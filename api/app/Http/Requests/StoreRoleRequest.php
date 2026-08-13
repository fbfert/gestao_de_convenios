<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // O nome e a chave de rota do papel (ver Route::bind('role')), por
            // isso o formato restrito: espaco e barra na URL viram dor de
            // cabeca sem trazer nada.
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique('roles', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('guard_name', 'web'),
            ],
            'copiar_de' => [
                'nullable',
                'string',
                Rule::exists('roles', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use apenas letras minúsculas, números e hífen no nome do papel.',
            'name.unique' => 'Já existe um papel com esse nome nesta clínica.',
            'copiar_de.exists' => 'O papel de origem não existe nesta clínica.',
        ];
    }
}
