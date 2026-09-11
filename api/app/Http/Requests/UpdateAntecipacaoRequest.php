<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição do registro de acompanhamento — só status (pendente ↔ ignorada) e
 * observações. Virar `gerada` só acontece por PATCH /marcar-gerada, disparado
 * pelo próprio fluxo de criação da solicitação, nunca por edição manual.
 */
class UpdateAntecipacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:pendente,ignorada'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
