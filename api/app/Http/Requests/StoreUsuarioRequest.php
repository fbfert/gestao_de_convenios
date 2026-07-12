<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->where('guard_name', 'web');
                }),
            ],
            'profissional_id' => [
                Rule::requiredIf(fn () => $this->input('role') === 'profissional'),
                'nullable',
                'integer',
                Rule::exists('profissionais', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
