<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'profissional_id' => $this->profissional_id,
            'especialidade_id' => $this->especialidade_id,
            'convenio_id' => $this->convenio_id,
            'medico_id' => $this->medico_id,
            'cid' => $this->cid,
            'medico' => $this->whenLoaded('medico', fn () => [
                'id' => $this->medico->id,
                'nome' => $this->medico->nome,
                'crm' => $this->medico->crm,
                'especialidade_medica' => $this->medico->especialidade_medica,
            ]),
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente->id,
                'nome' => $this->paciente->nome,
                'carteirinha' => $this->paciente->carteirinha,
            ]),
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
                'connector_type' => $this->convenio->connector_type,
                'connector_driver' => $this->convenio->connector_driver,
            ]),
            'guia' => $this->relationLoaded('guia') && $this->guia
                ? GuiaResource::make($this->guia)
                : null,
            'itens' => $this->whenLoaded('itens', fn () => $this->itens->map(fn ($item) => [
                'id' => $item->id,
                'especialidade_id' => $item->especialidade_id,
                'profissional_id' => $item->profissional_id,
                'quantidade' => $item->quantidade,
                'status_operacional' => $item->status_operacional,
                'observacoes' => $item->observacoes,
                'guia_id' => $item->relationLoaded('guia') && $item->guia ? $item->guia->id : null,
                'automacao_execucao_ativa' => $item->relationLoaded('automacaoExecucoes')
                    ? $item->automacaoExecucoes
                        ->whereIn('status', ['queued', 'running', 'uncertain'])
                        ->sortByDesc('id')
                        ->map(fn ($execucao) => [
                            'id' => $execucao->id,
                            'operacao' => $execucao->operacao,
                            'status' => $execucao->status,
                            'queued_at' => $execucao->queued_at?->toISOString(),
                        ])
                        ->first()
                    : null,
                'especialidade' => $item->relationLoaded('especialidade') && $item->especialidade ? [
                    'id' => $item->especialidade->id,
                    'nome' => $item->especialidade->nome,
                    'mapeamento_convenio' => $this->mapeamentoEspecialidade($item),
                ] : null,
                'profissional' => $item->relationLoaded('profissional') && $item->profissional ? [
                    'id' => $item->profissional->id,
                    'nome' => $item->profissional->nome,
                ] : null,
                'documentos' => $item->relationLoaded('documentos')
                    ? $item->documentos->map(fn ($documento) => $this->documento($documento))->values()
                    : [],
            ])->values()),
            'documentos' => $this->whenLoaded('documentos', fn () => $this->documentos
                ->map(fn ($documento) => $this->documento($documento))
                ->values()),
            'status' => $this->status,
            'solicitado_em' => $this->solicitado_em?->toDateString(),
            'observacoes' => $this->observacoes,
            'pedido_medico' => $this->pedido_medico_path ? [
                'nome_original' => $this->pedido_medico_nome_original,
                'mime' => $this->pedido_medico_mime,
                'url' => url("/api/solicitacoes/{$this->id}/pedido-medico"),
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function documento($documento): array
    {
        return [
            'id' => $documento->id,
            'solicitacao_item_id' => $documento->solicitacao_item_id,
            'tipo' => $documento->tipo,
            'nome_original' => $documento->nome_original,
            'mime' => $documento->mime,
            'url' => url("/api/solicitacoes/{$documento->solicitacao_id}/documentos/{$documento->id}"),
        ];
    }

    private function mapeamentoEspecialidade($item): ?array
    {
        if (! $this->convenio_id || ! $item->especialidade_id) {
            return null;
        }

        $mapeamento = $item->especialidade?->convenioMapeamentos
            ?->firstWhere('convenio_id', $this->convenio_id);

        if (! $mapeamento || ! $mapeamento->ativo) {
            return null;
        }

        return [
            'codigo_procedimento' => $mapeamento->codigo_procedimento,
            'descricao_operadora' => $mapeamento->descricao_operadora,
        ];
    }
}
