<?php

namespace App\Http\Resources;

use App\Models\AutomacaoExecucao;
use App\Support\GuiaStatus;
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
            'cids' => $this->whenLoaded('cidCadastros', fn () => $this->cidCadastros->map(fn ($cid) => [
                'id' => $cid->id,
                'codigo' => $cid->codigo,
                'descricao' => $cid->descricao,
            ])->values()),
            'medico' => $this->whenLoaded('medico', fn () => [
                'id' => $this->medico->id,
                'nome' => $this->medico->nome,
                'crm' => $this->medico->crm,
                'crm_uf' => $this->medico->crm_uf,
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
            'itens' => $this->whenLoaded('itens', fn () => $this->itens->map(fn ($item) => [
                'id' => $item->id,
                'especialidade_id' => $item->especialidade_id,
                'profissional_id' => $item->profissional_id,
                'quantidade' => $item->quantidade,
                'status_operacional' => $item->status_operacional,
                'observacoes' => $item->observacoes,
                'renovacao_de_item_id' => $item->renovacao_de_item_id,
                /*
                 * Posicao na cadeia de renovacao: 1 para a origem, 2 para a
                 * primeira renovacao, e assim por diante. Sem isto a tela
                 * mostraria duas linhas identicas (mesma especialidade, mesmo
                 * profissional) e ninguem entenderia por que sao duas.
                 *
                 * Calculada sobre a colecao ja carregada, e nao por consulta:
                 * os itens da solicitacao estao todos aqui.
                 */
                'posicao_na_cadeia' => $this->posicaoNaCadeia($item),
                'total_na_cadeia' => $this->cadeiaDe($item)->count(),
                /*
                 * A guia DO ITEM — e nao a relacao legada Solicitacao::guia(),
                 * que e um hasOne sem ordenacao e devolve uma guia qualquer da
                 * solicitacao. Desde a multi-especialidade cada item tem a sua.
                 *
                 * `numero_operadora` vem separado de `numero_guia` de proposito:
                 * o segundo pode conter o valor de preenchimento do convenio
                 * manual, e a tela nao deve exibi-lo como se fosse o numero que
                 * a operadora conhece. Quem decide isso e o backend, uma vez, em
                 * vez de cada tela repetir a regra.
                 */
                'guia' => $item->relationLoaded('guia') && $item->guia ? [
                    'id' => $item->guia->id,
                    'numero_guia' => $item->guia->numero_guia,
                    'numero_operadora' => GuiaStatus::numeroDaOperadora($item->guia->numero_guia),
                    'status' => $item->guia->status,
                    // É por esta data que a tela decide recolher o item: guia
                    // finalizada na operadora não tem mais ação pendente aqui.
                    'finalizada_na_operadora_em' => $item->guia->finalizada_na_operadora_em?->toISOString(),
                ] : null,
                'automacao_execucao_ativa' => $item->relationLoaded('automacaoExecucoes')
                    ? AutomacaoExecucao::ativaMaisRecente($item->automacaoExecucoes)
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
            'pedido_medico' => $this->pedidoMedico(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Os itens da mesma cadeia de renovação, da origem para a última.
     *
     * A cadeia é PLANA: todo item de renovação aponta para a origem, nunca para
     * o anterior (ver SolicitacaoService::origemDaCadeia). Por isso agrupar por
     * "id da origem, ou o próprio id" basta, e não há recursão.
     */
    private function cadeiaDe($item)
    {
        $origemId = $item->renovacao_de_item_id ?? $item->id;

        return $this->itens
            ->filter(fn ($outro) => ($outro->renovacao_de_item_id ?? $outro->id) === $origemId)
            ->sortBy('id')
            ->values();
    }

    private function posicaoNaCadeia($item): int
    {
        $posicao = $this->cadeiaDe($item)->search(fn ($outro) => $outro->id === $item->id);

        return $posicao === false ? 1 : $posicao + 1;
    }

    /**
     * Deriva do vínculo (tipo=pedido_medico, sem item) em vez de colunas
     * legadas na Solicitação — toda solicitação tem esse vínculo, inclusive
     * as anteriores à tabela solicitacao_documentos (ver backfill).
     */
    private function pedidoMedico(): ?array
    {
        if (! $this->relationLoaded('documentos')) {
            return null;
        }

        $documento = $this->documentos
            ->whereNull('solicitacao_item_id')
            ->first(fn ($documento) => $documento->arquivo?->tipo === 'pedido_medico');

        if (! $documento) {
            return null;
        }

        return [
            'nome_original' => $documento->arquivo->nome_original,
            'mime' => $documento->arquivo->mime,
            'url' => url("/api/solicitacoes/{$documento->solicitacao_id}/documentos/{$documento->id}"),
        ];
    }

    private function documento($documento): array
    {
        return [
            'id' => $documento->id,
            'solicitacao_item_id' => $documento->solicitacao_item_id,
            'tipo' => $documento->arquivo->tipo,
            'nome_original' => $documento->arquivo->nome_original,
            'mime' => $documento->arquivo->mime,
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
