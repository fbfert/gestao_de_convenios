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
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'especialidade_id' => ['required', 'integer', 'exists:especialidades,id'],
            'convenio_id' => ['required', 'integer', 'exists:convenios,id'],
            'medico_id' => [
                'required',
                'integer',
                Rule::exists('medicos', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'solicitado_em' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
