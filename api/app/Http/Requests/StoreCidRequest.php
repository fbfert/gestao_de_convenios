<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => trim((string) $this->input('codigo')),
            'descricao' => trim((string) $this->input('descricao')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cids', 'codigo')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'descricao' => ['required', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
