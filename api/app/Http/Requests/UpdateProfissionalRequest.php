<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfissionalRequest extends FormRequest
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
            'especialidade_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'conselho_registro' => ['sometimes', 'nullable', 'string', 'max:255'],
            'percentual_repasse' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
