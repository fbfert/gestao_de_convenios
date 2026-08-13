<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique('roles', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('guard_name', 'web')
                    ->ignore($this->route('role')?->getKey()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use apenas letras minúsculas, números e hífen no nome do papel.',
            'name.unique' => 'Já existe um papel com esse nome nesta clínica.',
        ];
    }
}
