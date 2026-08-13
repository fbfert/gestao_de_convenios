<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosDePaciente;
use App\Http\Requests\Concerns\ValidaCarteirinhaPorConvenio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    use DadosDePaciente, ValidaCarteirinhaPorConvenio;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'carteirinha' => ['sometimes', 'required', 'string', 'max:255'],
            'convenio_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'ativo' => ['sometimes', 'boolean'],
            ...$this->regrasDeContato(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararCarteirinha();
        $this->limparContato();
    }
}
