<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConvenioProfissionalMapeamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();
        $ignoreId = $this->route('profissionalMapeamento')?->id;

        return [
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'profissional_id' => [
                'required',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                Rule::unique('convenio_profissional_mapeamentos', 'profissional_id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('convenio_id', $this->input('convenio_id')))
                    ->ignore($ignoreId),
            ],
            'codigo_operadora' => ['required', 'string', 'max:255'],
            'nome_operadora' => ['nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
