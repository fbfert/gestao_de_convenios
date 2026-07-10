<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'data_sessao' => ['required', 'date'],
        ];
    }
}
