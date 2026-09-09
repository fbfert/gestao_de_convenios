<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Item acrescentado a uma solicitação que já existe.
 *
 * Repare no que NÃO está aqui: nenhuma regra impedindo especialidade e
 * profissional repetidos. É deliberado — repetir o mesmo par é o caso de uso
 * principal desta feature (10 sessões por guia, paciente que precisa de 20).
 * A tela avisa; a validação não bloqueia.
 *
 * `quantidade` é opcional e vira a das sessões por guia da regra vigente do
 * convênio. Sem regra vigente o serviço RECUSA em vez de arbitrar um número —
 * ver `SolicitacaoService::quantidadeDoItem()`.
 */
class StoreSolicitacaoItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'especialidade_id' => [
                'required',
                'integer',
                Rule::exists('especialidades', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'profissional_id' => [
                'required',
                'integer',
                Rule::exists('profissionais', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'quantidade' => ['nullable', 'integer', 'min:1'],
            'observacoes' => ['nullable', 'string'],
            // Escopado ao tenant aqui; que seja da MESMA solicitação é conferido
            // no serviço, que é quem conhece a solicitação — e é lá também que o
            // valor é normalizado para a origem da cadeia.
            'renovacao_de_item_id' => [
                'nullable',
                'integer',
                Rule::exists('solicitacao_itens', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
