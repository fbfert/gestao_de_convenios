<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo')) {
            $this->merge(['codigo' => trim((string) $this->input('codigo'))]);
        }

        if ($this->has('descricao')) {
            $this->merge(['descricao' => trim((string) $this->input('descricao'))]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $cid = $this->route('cid');

        return [
            'codigo' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('cids', 'codigo')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($cid?->id),
            ],
            'descricao' => ['sometimes', 'required', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
