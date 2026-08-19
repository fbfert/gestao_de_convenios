<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edicao manual de uma guia ja registrada, restrita a `guias.manage`.
 * Paciente, convenio, status e os vinculos com solicitacao/automacao ficam de
 * fora de proposito: paciente/convenio sao identidade com dados gravados em
 * Antecipacao/Conciliacao ja geradas, status muda so por Finalizar/Negar, e os
 * campos unimed_* pertencem a automacao (editar a mao os desalinha do portal).
 */
class UpdateGuiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'profissional_id' => [
                'sometimes',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'especialidade_id' => [
                'sometimes',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'numero_guia' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tipo_terapia' => ['sometimes', 'in:especializada,convencional,outro'],
            'data_solicitacao' => ['sometimes', 'date'],
            'data_finalizacao' => ['sometimes', 'nullable', 'date'],
            'sessoes_solicitadas' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sessoes_autorizadas' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'protocolo_operadora' => ['sometimes', 'nullable', 'string', 'max:255'],
            'senha' => ['sometimes', 'nullable', 'string', 'max:255'],
            'validade_senha' => ['sometimes', 'nullable', 'date'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
