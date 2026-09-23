<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExisteNaClinica;
use Illuminate\Foundation\Http\FormRequest;

class ListConciliacaoRequest extends FormRequest
{
    use ExisteNaClinica;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'convenio_id' => ['nullable', 'integer', $this->existeNaClinica('convenios')],
            'especialidade_id' => ['nullable', 'integer', $this->existeNaClinica('especialidades')],
            'profissional_id' => ['nullable', 'integer', $this->existeNaClinica('profissionais')],
            'status' => ['nullable', 'in:pending,reviewed,paid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
