<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('tenants', 'slug'),
            ],
            'cnpj' => ['nullable', 'string', 'max:32'],
            'ativo' => ['required', 'boolean'],

            // Primeiro usuário da clínica. Sem ele o tenant nasce inacessível:
            // não há como entrar num tenant que não tem nenhuma conta, e a tela
            // de Usuários só cria gente no tenant de quem está logado.
            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:255'],
            // E-mail é único globalmente, não por tenant: o login busca só por
            // e-mail, antes de existir tenant resolvido (ADR-11).
            'admin.email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'admin.password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O identificador aceita apenas letras minúsculas, números e hífen, começando por letra.',
            'slug.unique' => 'Já existe uma clínica com este identificador.',
            'admin.email.unique' => 'Este e-mail já pertence a um usuário. O e-mail é único entre todas as clínicas.',
            'admin.password.min' => 'A senha do administrador precisa de pelo menos 8 caracteres.',
        ];
    }
}
