<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LerRegistroSessoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mesmos formatos das outras leituras por IA. Registro de sessões
            // costuma vir como foto do papel assinado ou PDF escaneado.
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }
}
