<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solicitacao_id' => ['nullable', 'integer', 'exists:solicitacoes,id'],
            'convenio_id' => ['required', 'integer', 'exists:convenios,id'],
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'numero_guia' => ['required', 'string', 'max:255'],
            'tipo_terapia' => ['required', 'in:especializada,convencional,outro'],
            'data_solicitacao' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
