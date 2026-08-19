<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edicao manual de uma antecipacao, restrita a `antecipacoes.manage`.
 *
 * `qtd_utilizada`, `status` e os vinculos (guia_id/paciente_id/convenio_id)
 * ficam de fora de proposito: sao mantidos automaticamente por
 * AntecipacaoService::consumirCota() e LancamentoService::remover() a partir
 * das sessoes lancadas/removidas. Editar esses campos a mao descolaria a cota
 * do que foi realmente lancado — a correcao de quantidade usada continua
 * sendo feita lancando ou removendo sessoes em Sessoes.
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
            'qtd_autorizada' => ['sometimes', 'integer', 'min:1'],
            'ciclo_inicio' => ['sometimes', 'date'],
            'ciclo_fim' => ['sometimes', 'date'],
        ];
    }
}
