<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:255'],
            'carteirinha' => ['required', 'string', 'max:255'],
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'telefone' => ['nullable', 'string', 'max:255'],
            'clinica_agil_id' => ['nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
