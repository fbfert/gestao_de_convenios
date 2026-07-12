<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'crm' => ['required', 'string', 'max:255'],
            'especialidade_medica' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
