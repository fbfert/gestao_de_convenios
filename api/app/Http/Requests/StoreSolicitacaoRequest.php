<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'convenio_id' => ['required', 'integer', 'exists:convenios,id'],
            'medico_solicitante' => ['required', 'string', 'max:255'],
            'solicitado_em' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
