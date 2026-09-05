<?php

namespace App\Concerns;

use App\Models\PacienteArquivo;
use Illuminate\Validation\Validator;

/**
 * Regra compartilhada entre anexar um arquivo novo (StoreSolicitacaoDocumentoRequest)
 * e vincular um arquivo já existente da pasta do paciente
 * (VincularSolicitacaoDocumentoRequest): o tipo do documento decide se ele é
 * da solicitação inteira ou de uma especialidade (item) dela.
 */
trait ValidaTipoESolicitacaoItemDoDocumento
{
    private function validarTipoEItem(Validator $validator, string $tipo, mixed $itemId): void
    {
        if (! in_array($tipo, PacienteArquivo::TIPOS, true)) {
            return;
        }

        $exigeItem = in_array($tipo, PacienteArquivo::TIPOS_POR_ITEM, true);

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
    }
}
