<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListConciliacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'convenio_id' => ['nullable', 'integer', 'exists:convenios,id'],
            'especialidade_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'profissional_id' => ['nullable', 'integer', 'exists:profissionais,id'],
            'status' => ['nullable', 'in:pending,reviewed,paid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
