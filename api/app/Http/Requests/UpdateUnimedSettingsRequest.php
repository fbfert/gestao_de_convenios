<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnimedSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'convenio_id' => [
                'nullable',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'credential' => ['required', 'array'],
            'credential.login' => ['required', 'string', 'max:255'],
            'credential.password' => ['nullable', 'string', 'max:2000'],
            'credential.base_url' => ['nullable', 'url', 'max:255'],
            'credential.nome_contratado' => ['nullable', 'string', 'max:255'],
            'credential.ativo' => ['required', 'boolean'],
        ];
    }
}
