<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfissionalRequest extends FormRequest
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
            'especialidade_id' => [
                'required',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'conselho_registro' => ['nullable', 'string', 'max:255'],
            'percentual_repasse' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
