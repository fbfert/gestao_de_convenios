<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'solicitacao_id' => [
                'nullable',
                'integer',
                Rule::exists('solicitacoes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'solicitacao_item_id' => [
                'nullable',
                'integer',
                Rule::exists('solicitacao_itens', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'paciente_id' => [
                'required',
                'integer',
                Rule::exists('pacientes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'profissional_id' => [
                'required',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'especialidade_id' => [
                'required',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'numero_guia' => ['required', 'string', 'max:255'],
            'tipo_terapia' => ['required', 'in:especializada,convencional,outro'],
            'data_solicitacao' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
