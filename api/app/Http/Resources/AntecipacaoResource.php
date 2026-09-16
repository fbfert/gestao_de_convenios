<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AntecipacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'data_alvo' => $this->data_alvo?->toDateString(),
            'itens_selecionados' => $this->itens_selecionados,
            // Resolvido pelo service para a página inteira. O
            // `itens_selecionados` acima continua sendo o retrato do momento
            // da geração; este traz a guia como ela está agora.
            'itens_gerados' => $this->whenNotNull($this->itens_gerados),
            'observacoes' => $this->observacoes,
            'gerado_em' => $this->gerado_em?->toISOString(),
            'ignorado_em' => $this->ignorado_em?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'solicitacao_origem' => $this->whenLoaded('solicitacaoOrigem', fn () => [
                'id' => $this->solicitacaoOrigem->id,
                'paciente' => $this->solicitacaoOrigem->paciente ? [
                    'id' => $this->solicitacaoOrigem->paciente->id,
                    'nome' => $this->solicitacaoOrigem->paciente->nome,
                ] : null,
                'convenio' => $this->solicitacaoOrigem->convenio ? [
                    'id' => $this->solicitacaoOrigem->convenio->id,
                    'nome' => $this->solicitacaoOrigem->convenio->nome,
                ] : null,
            ]),
            'criado_por' => $this->whenLoaded('criadoPor', fn () => $this->criadoPor ? [
                'id' => $this->criadoPor->id,
                'nome' => $this->criadoPor->name,
            ] : null),
        ];
    }
}
