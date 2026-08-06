<?php

namespace App\Http\Requests;

use App\Models\SolicitacaoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSolicitacaoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 5 MB é o teto aceito pelo portal da Unimed (worker-unimed/src/operations/gerarGuia.js),
            // então recusamos aqui para o operador descobrir na hora do anexo, não no envio.
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif', 'max:5120'],
            'tipo' => ['required', 'string', Rule::in(SolicitacaoDocumento::TIPOS)],
            'solicitacao_item_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.mimes' => 'O anexo precisa ser uma imagem (JPG, PNG, GIF) ou PDF.',
            'arquivo.max' => 'O anexo não pode passar de 5 MB, limite aceito pelo portal da Unimed.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipo = (string) $this->input('tipo');
            $itemId = $this->input('solicitacao_item_id');

            if (! in_array($tipo, SolicitacaoDocumento::TIPOS, true)) {
                return;
            }

            $exigeItem = in_array($tipo, SolicitacaoDocumento::TIPOS_POR_ITEM, true);

            if ($exigeItem && ! $itemId) {
                $validator->errors()->add(
                    'solicitacao_item_id',
                    'Este anexo precisa estar vinculado a uma especialidade da solicitação.',
                );

                return;
            }

            if (! $exigeItem && $itemId) {
                $validator->errors()->add(
                    'solicitacao_item_id',
                    'Este anexo pertence à solicitação inteira e não a uma especialidade.',
                );

                return;
            }

            if (! $itemId) {
                return;
            }

            $solicitacao = $this->route('solicitacao');
            $pertence = $solicitacao?->itens()->whereKey($itemId)->exists();

            if (! $pertence) {
                $validator->errors()->add(
                    'solicitacao_item_id',
                    'A especialidade informada não pertence a esta solicitação.',
                );
            }
        });
    }
}
