<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'crm_uf' => $this->crm_uf ? strtoupper(trim((string) $this->crm_uf)) : $this->crm_uf,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            // Só dígitos: o portal da Unimed busca prestador pelo número do
            // CRM puro. Um valor como "CRM-SC 12345" nesse campo falha a
            // busca em silêncio no automatizador — a UF vira campo próprio.
            'crm' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'crm_uf' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'especialidade_medica' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'crm.regex' => 'Informe apenas os números do CRM, sem prefixos ou a UF.',
            'crm_uf.regex' => 'Informe a UF do CRM com 2 letras, ex. SC.',
        ];
    }
}
