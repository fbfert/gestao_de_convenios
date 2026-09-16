<?php

namespace App\Http\Requests;

use App\Models\Antecipacao;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros do histórico de antecipações.
 *
 * `data_de`/`data_ate` recaem sobre `created_at` — a data em que alguém
 * gerou ou ignorou, que é a que a linha do histórico exibe. Não confundir
 * com `data_alvo`, que é a data prevista da antecipação: as duas quase nunca
 * coincidem, já que uma entrada pode ficar dias na fila antes de alguém
 * decidir.
 */
class ListarAntecipacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'status' => ['nullable', Rule::in([Antecipacao::STATUS_GERADA, Antecipacao::STATUS_IGNORADA])],
            'paciente_nome' => ['nullable', 'string', 'max:255'],
            'numero_guia' => ['nullable', 'string', 'max:255'],
            'convenio_id' => [
                'nullable', 'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'data_de' => ['nullable', 'date'],
            'data_ate' => ['nullable', 'date', 'after_or_equal:data_de'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_ate.after_or_equal' => 'A data final do período não pode ser anterior à inicial.',
        ];
    }
}
