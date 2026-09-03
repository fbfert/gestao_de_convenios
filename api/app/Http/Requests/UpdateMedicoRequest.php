<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('crm_uf') && $this->crm_uf) {
            $this->merge(['crm_uf' => strtoupper(trim((string) $this->crm_uf))]);
        }
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'crm' => ['sometimes', 'required', 'string', 'regex:/^[0-9]+$/'],
            'crm_uf' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'especialidade_medica' => ['sometimes', 'required', 'string', 'max:255'],
            'telefone' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
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
