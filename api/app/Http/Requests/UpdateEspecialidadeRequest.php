<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEspecialidadeRequest extends FormRequest
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
        $especialidade = $this->route('especialidade');

        return [
            'nome' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('especialidades', 'nome')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($especialidade?->id),
            ],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
