<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:32'],
            'ativo' => ['required', 'boolean'],
            // `slug` não entra: é o identificador estável da clínica, usado em
            // consulta por seeders e migrations (ex.: 'clinica-exemplo').
            // Trocá-lo depois de criado quebraria essas buscas em silêncio.
        ];
    }
}
