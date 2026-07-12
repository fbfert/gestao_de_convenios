<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();
        $usuarioId = $this->route('usuario')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuarioId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('roles', 'name')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->where('guard_name', 'web');
                }),
            ],
            'profissional_id' => [
                'sometimes',
                Rule::requiredIf(fn () => $this->input('role', $this->route('usuario')?->roles->first()?->name) === 'profissional'),
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
