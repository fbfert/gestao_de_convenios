<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEspecialidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nome')) {
            $this->merge([
                'nome' => trim((string) $this->input('nome')),
            ]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('especialidades', 'nome')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'ativo' => ['sometimes', 'boolean'],

            // Um codigo por convenio: a lista chega inteira, e codigo em branco
            // significa que a especialidade nao existe naquele convenio.
            'codigos' => ['sometimes', 'array'],
            'codigos.*.convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'codigos.*.codigo' => ['nullable', 'string', 'max:50'],
        ];
    }
}
