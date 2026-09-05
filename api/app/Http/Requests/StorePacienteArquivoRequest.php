<?php

namespace App\Http\Requests;

use App\Models\PacienteArquivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePacienteArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif', 'max:5120'],
            'tipo' => ['required', 'string', Rule::in(PacienteArquivo::TIPOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.mimes' => 'O anexo precisa ser uma imagem (JPG, PNG, GIF) ou PDF.',
            'arquivo.max' => 'O anexo não pode passar de 5 MB.',
        ];
    }
}
