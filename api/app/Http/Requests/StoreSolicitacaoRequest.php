<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'paciente_id' => [
                'required',
                'integer',
                Rule::exists('pacientes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'profissional_id' => [
                'required_without:itens',
                'nullable',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'especialidade_id' => [
                'required_without:itens',
                'nullable',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'medico_id' => [
                'required',
                'integer',
                Rule::exists('medicos', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'cid_ids' => ['required', 'array', 'min:1'],
            'cid_ids.*' => [
                'integer',
                Rule::exists('cids', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'solicitado_em' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
            'pedido_medico_upload_id' => ['nullable', 'string', 'max:500'],
            'pedido_medico_nome_original' => ['nullable', 'string', 'max:255'],
            'pedido_medico_mime' => ['nullable', 'string', 'max:255'],
            'pedido_medico_ai_result' => ['nullable', 'array'],
            'itens' => ['nullable', 'array', 'min:1'],
            'itens.*.especialidade_id' => [
                'required_with:itens',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'itens.*.profissional_id' => [
                'required_with:itens',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'itens.*.quantidade' => ['nullable', 'integer', 'min:1'],
            'itens.*.observacoes' => ['nullable', 'string'],
        ];
    }
}
