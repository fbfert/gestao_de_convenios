<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarImportConciliacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'linha_ids' => ['present', 'array'],
            'linha_ids.*' => ['integer'],
            'edicoes' => ['sometimes', 'array'],
        ];
    }
}
