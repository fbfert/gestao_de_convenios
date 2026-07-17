<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['sometimes', 'integer', 'exists:profissionais,id'],
            'data_sessao' => ['sometimes', 'date'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
            'hora_fim' => ['sometimes', 'nullable', 'date_format:H:i'],
            'acompanhante' => ['sometimes', 'nullable', 'string', 'max:255'],
            'resumo_atividades' => ['sometimes', 'nullable', 'string'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
