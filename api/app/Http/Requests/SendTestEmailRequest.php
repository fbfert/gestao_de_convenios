<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail que deve receber o teste.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ];
    }
}
