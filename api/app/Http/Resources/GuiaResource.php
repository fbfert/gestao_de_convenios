<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitacao_id' => $this->solicitacao_id,
            'solicitacao_item_id' => $this->solicitacao_item_id,
            'automacao_execucao_id' => $this->automacao_execucao_id,
            'convenio_id' => $this->convenio_id,
            'paciente_id' => $this->paciente_id,
            'profissional_id' => $this->profissional_id,
            'especialidade_id' => $this->especialidade_id,
            'numero_guia' => $this->numero_guia,
            'tipo_terapia' => $this->tipo_terapia,
            'status' => $this->status,
            'unimed_status' => $this->unimed_status,
            'unimed_last_checked_at' => $this->unimed_last_checked_at?->toISOString(),
            'unimed_next_check_at' => $this->unimed_next_check_at?->toISOString(),
            'data_solicitacao' => $this->data_solicitacao?->toDateString(),
            'data_finalizacao' => $this->data_finalizacao?->toDateString(),
            'senha' => $this->senha,
            'validade_senha' => $this->validade_senha?->toDateString(),
            'observacoes' => $this->observacoes,
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente->id,
                'nome' => $this->paciente->nome,
                'carteirinha' => $this->paciente->carteirinha,
            ]),
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
            ]),
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
            ]),
            'especialidade' => $this->whenLoaded('especialidade', fn () => [
                'id' => $this->especialidade->id,
                'nome' => $this->especialidade->nome,
            ]),
            'solicitacao_item' => $this->whenLoaded('solicitacaoItem', fn () => $this->solicitacaoItem ? [
                'id' => $this->solicitacaoItem->id,
                'especialidade_id' => $this->solicitacaoItem->especialidade_id,
                'profissional_id' => $this->solicitacaoItem->profissional_id,
                'quantidade' => $this->solicitacaoItem->quantidade,
                'status_operacional' => $this->solicitacaoItem->status_operacional,
                'especialidade' => $this->solicitacaoItem->relationLoaded('especialidade') && $this->solicitacaoItem->especialidade ? [
                    'id' => $this->solicitacaoItem->especialidade->id,
                    'nome' => $this->solicitacaoItem->especialidade->nome,
                ] : null,
                'profissional' => $this->solicitacaoItem->relationLoaded('profissional') && $this->solicitacaoItem->profissional ? [
                    'id' => $this->solicitacaoItem->profissional->id,
                    'nome' => $this->solicitacaoItem->profissional->nome,
                ] : null,
            ] : null),
            'automacao_execucao' => $this->whenLoaded('automacaoExecucao', fn () => $this->automacaoExecucao ? [
                'id' => $this->automacaoExecucao->id,
                'operacao' => $this->automacaoExecucao->operacao,
                'status' => $this->automacaoExecucao->status,
                'erro_codigo' => $this->automacaoExecucao->erro_codigo,
                'erro_mensagem' => $this->automacaoExecucao->erro_mensagem,
                'queued_at' => $this->automacaoExecucao->queued_at?->toISOString(),
                'started_at' => $this->automacaoExecucao->started_at?->toISOString(),
                'finished_at' => $this->automacaoExecucao->finished_at?->toISOString(),
                'eventos' => $this->automacaoExecucao->relationLoaded('eventos')
                    ? $this->automacaoExecucao->eventos->map(fn ($evento) => [
                        'id' => $evento->id,
                        'tipo' => $evento->tipo,
                        'status' => $evento->status,
                        'registrado_em' => $evento->registrado_em?->toISOString(),
                    ])->values()
                    : [],
            ] : null),
            'antecipacoes' => $this->whenLoaded('antecipacoes', fn () => $this->antecipacoes->map(fn ($antecipacao) => [
                'id' => $antecipacao->id,
                'qtd_autorizada' => $antecipacao->qtd_autorizada,
                'qtd_utilizada' => $antecipacao->qtd_utilizada,
                'status' => $antecipacao->status,
            ])->values()),
            'conciliacoes' => $this->whenLoaded('conciliacoes', fn () => $this->conciliacoes->map(fn ($conciliacao) => [
                'id' => $conciliacao->id,
                'status' => $conciliacao->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
