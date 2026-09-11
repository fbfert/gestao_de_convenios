<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição do registro de acompanhamento — só observações. `gerada` e
 * `ignorada` são status terminais, escritos só por `criar()`/`ignorar()`
 * (ver AntecipacaoService); não há edição manual de status.
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
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
