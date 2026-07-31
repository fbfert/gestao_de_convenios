<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomacaoExecucaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operacao' => $this->operacao,
            'status' => $this->status,
            'solicitacao_item_id' => $this->solicitacao_item_id,
            'guia_id' => $this->guia_id,
            'parent_id' => $this->parent_id,
            'erro_codigo' => $this->erro_codigo,
            'erro_mensagem' => $this->erro_mensagem,
            'payload' => $this->payload,
            'resultado' => $this->resultado,
            'queued_at' => $this->queued_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'guia' => $this->whenLoaded('guia', fn () => $this->guia ? [
                'id' => $this->guia->id,
                'numero_guia' => $this->guia->numero_guia,
                'status' => $this->guia->status,
            ] : null),
            'solicitacao_item' => $this->whenLoaded('solicitacaoItem', fn () => $this->solicitacaoItem ? [
                'id' => $this->solicitacaoItem->id,
                'status_operacional' => $this->solicitacaoItem->status_operacional,
                'quantidade' => $this->solicitacaoItem->quantidade,
            ] : null),
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos->map(fn ($evento) => [
                'id' => $evento->id,
                'tipo' => $evento->tipo,
                'status' => $evento->status,
                'payload' => $evento->payload,
                'evidencias' => $evento->evidencias,
                'registrado_em' => $evento->registrado_em?->toISOString(),
            ])->values()),
        ];
    }
}
