<?php

namespace App\Http\Resources;

use App\Services\SolicitacaoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'connector_type' => $this->connector_type,
            'connector_driver' => $this->connector_driver,
            'carteirinha_blocos' => $this->blocosCarteirinha(),
            /*
             * Sessoes por guia da regra VIGENTE — e nulo quando nao ha regra
             * cadastrada, que e resposta legitima e nao erro.
             *
             * Vem daqui para o formulario poder pre-preencher a quantidade em
             * vez de o front repetir a regra. Sem isto, o campo nasceria com um
             * literal (era '10' em tres lugares do front) e a tela mostraria um
             * numero que a API recusa — a pessoa veria dez e levaria um erro
             * dizendo que faltou preencher.
             */
            'sessoes_por_guia' => app(SolicitacaoService::class)->quantidadePadrao((int) $this->id),
            // Nulo = usa o padrão de configuracoes_globais (ver Guia::antecipacaoDataAlvo()).
            'antecipacao_dias' => $this->antecipacao_dias,
            'antecipacao_referencia' => $this->antecipacao_referencia,
            'ativo' => $this->ativo,
        ];
    }
}
