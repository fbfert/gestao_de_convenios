<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConvenioEspecialidadeMapeamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();
        $ignoreId = $this->route('especialidadeMapeamento')?->id;

        return [
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'especialidade_id' => [
                'required',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                Rule::unique('convenio_especialidade_mapeamentos', 'especialidade_id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('convenio_id', $this->input('convenio_id')))
                    ->ignore($ignoreId),
            ],
            'codigo_procedimento' => ['required', 'string', 'max:255'],
            'descricao_operadora' => ['nullable', 'string', 'max:255'],
            'quantidade_padrao' => ['sometimes', 'integer', 'min:1'],
            'usa_descricao_generica' => ['sometimes', 'boolean'],
            'valor_generico' => ['nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
