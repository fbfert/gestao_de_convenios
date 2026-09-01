<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportAntecipacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
