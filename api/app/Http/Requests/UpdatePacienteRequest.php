<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'cpf' => ['sometimes', 'nullable', 'string', 'max:255'],
            'carteirinha' => ['sometimes', 'required', 'string', 'max:255'],
            'convenio_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clinica_agil_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
