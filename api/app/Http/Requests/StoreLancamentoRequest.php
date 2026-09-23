<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExisteNaClinica;
use Illuminate\Foundation\Http\FormRequest;

class StoreLancamentoRequest extends FormRequest
{
    use ExisteNaClinica;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', $this->existeNaClinica('profissionais')],
            'data_sessao' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'acompanhante' => ['nullable', 'string', 'max:255'],
            'resumo_atividades' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
