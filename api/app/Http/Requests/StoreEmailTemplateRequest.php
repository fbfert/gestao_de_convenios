<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $template = $this->route('emailTemplate');

        return [
            'chave' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('email_templates', 'chave')
                    ->where('tenant_id', (int) $this->user()->tenant_id)
                    ->ignore($template?->id),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'assunto' => ['required', 'string', 'max:255'],
            'corpo' => ['required', 'string'],
            'ativo' => ['required', 'boolean'],
        ];
    }
}
