<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IgnorarAntecipacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'solicitacao_origem_id' => [
                'required', 'integer',
                Rule::exists('solicitacoes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'data_alvo' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
