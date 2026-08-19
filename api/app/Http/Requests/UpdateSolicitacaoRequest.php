<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edicao manual de uma solicitacao ja registrada, restrita a
 * `solicitacoes.manage`. Paciente e convenio ficam de fora de proposito: sao
 * campos de identidade com dados gravados (snapshot) em Guia/Antecipacao ja
 * geradas a partir deles.
 */
class UpdateSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'medico_id' => [
                'sometimes',
                'integer',
                Rule::exists('medicos', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'cid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'solicitado_em' => ['sometimes', 'date'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
