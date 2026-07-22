<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'smtp' => ['required', 'array'],
            'smtp.host' => ['required', 'string', 'max:255'],
            'smtp.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp.username' => ['nullable', 'string', 'max:255'],
            'smtp.password' => ['nullable', 'string', 'max:1000'],
            'smtp.encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp.from_email' => ['required', 'email', 'max:255'],
            'smtp.from_name' => ['nullable', 'string', 'max:255'],
            'smtp.ativo' => ['required', 'boolean'],
            'templates' => ['present', 'array'],
            'templates.*.chave' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/'],
            'templates.*.nome' => ['required', 'string', 'max:255'],
            'templates.*.assunto' => ['required', 'string', 'max:255'],
            'templates.*.corpo' => ['required', 'string'],
            'templates.*.ativo' => ['required', 'boolean'],
        ];
    }
}
