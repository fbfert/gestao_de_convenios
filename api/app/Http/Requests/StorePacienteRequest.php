<?php

namespace App\Http\Requests;

use App\Models\Convenio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:255'],
            'carteirinha' => ['required', 'string', 'max:255'],
            'convenio_id' => [
                'required',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'telefone' => ['nullable', 'string', 'max:255'],
            'clinica_agil_id' => ['nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $convenioId = $this->integer('convenio_id');

            if (! $convenioId || ! $this->user()?->tenant_id) {
                return;
            }

            $convenio = Convenio::query()
                ->where('tenant_id', $this->user()->tenant_id)
                ->whereKey($convenioId)
                ->first();

            if ($convenio?->connector_driver !== 'unimed_rda') {
                return;
            }

            $carteirinha = preg_replace('/\D+/', '', (string) $this->input('carteirinha'));

            if (strlen($carteirinha) !== 17) {
                $validator->errors()->add('carteirinha', 'A carteirinha Unimed deve conter 17 dígitos nos blocos 4+4+6+2+1.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('carteirinha_unimed'))) {
            $this->merge([
                'carteirinha' => implode('', $this->input('carteirinha_unimed')),
            ]);
        }

    }
}
