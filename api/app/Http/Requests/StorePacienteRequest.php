<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosDePaciente;
use App\Http\Requests\Concerns\ValidaCarteirinhaPorConvenio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePacienteRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255'],
            'carteirinha' => ['required', 'string', 'max:255'],
            'convenio_id' => [
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
