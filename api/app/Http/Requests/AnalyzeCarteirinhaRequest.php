<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeCarteirinhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mesmos formatos e teto do pedido médico. Foto de celular passa
            // folgado em 20 MB, e PDF entra porque parte das operadoras manda
            // a carteirinha digital nesse formato.
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }
}
