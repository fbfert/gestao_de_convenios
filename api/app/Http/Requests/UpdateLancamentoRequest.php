<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExisteNaClinica;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLancamentoRequest extends FormRequest
{
    use ExisteNaClinica;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['sometimes', 'integer', $this->existeNaClinica('profissionais')],
            'data_sessao' => ['sometimes', 'date'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
            'hora_fim' => ['sometimes', 'nullable', 'date_format:H:i'],
            'acompanhante' => ['sometimes', 'nullable', 'string', 'max:255'],
            'resumo_atividades' => ['sometimes', 'nullable', 'string'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
