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
            // A "atenção" some quando uma execução mais recente da MESMA guia já
            // teve sucesso — a guia se recuperou, e falhas antigas viram ruído
            // histórico, não algo que o operador ainda precisa tratar.
            'precisa_atencao' => in_array($this->status, ['failed', 'uncertain', 'needs_attention'], true)
                && ! ($this->guia_id && $this->guia_ultima_execucao_status === 'succeeded'),
            'solicitacao_item_id' => $this->solicitacao_item_id,
            'guia_id' => $this->guia_id,
            'parent_id' => $this->parent_id,
            // So no payload (`gerar_guia`) essas 3 informacoes vem prontas — as
            // outras 3 operacoes (consultar_status, capturar_senha_validade,
            // confirmar_guia_incerta) nao carregam medico/profissional no
            // payload. Le direto das relacoes pra funcionar igual pra
            // qualquer operacao, com a Guia como fallback quando nao ha
            // solicitacao_item (execucoes que operam sobre uma Guia ja
            // existente).
            'paciente_nome' => $this->solicitacaoItem?->solicitacao?->paciente?->nome
                ?? $this->guia?->paciente?->nome,
            'medico_nome' => $this->solicitacaoItem?->solicitacao?->medico?->nome
                ?? $this->guia?->solicitacaoItem?->solicitacao?->medico?->nome,
            'profissional_executante_nome' => $this->solicitacaoItem?->profissional?->nome
                ?? $this->guia?->profissional?->nome,
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
