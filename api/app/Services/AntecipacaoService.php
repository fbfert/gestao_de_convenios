<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\Guia;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Antecipação manual: gerar a solicitação do próximo ciclo, replicando os
 * itens de uma solicitação já em andamento. Duas fontes convivem na tela
 * /antecipacoes:
 *
 *   - "Elegíveis" — calculada ao vivo, sem tabela própria (ver
 *     Guia::elegiveisParaAntecipacao, a mesma lista que alimenta o alerta
 *     AntecipacaoDevida).
 *   - Histórico — registros `Antecipacao` persistidos, um por acionamento
 *     manual (da própria tela ou do botão em Solicitações), com status
 *     pendente/gerada/ignorada.
 */
class AntecipacaoService
{
    /**
     * Guias elegíveis agrupadas por solicitação de origem, excluindo as que
     * já têm uma Antecipacao pendente ou gerada em andamento (evita duplicar
     * a mesma solicitação na fila depois que alguém já iniciou).
     */
    public function listarElegiveis(int $tenantId): array
    {
        $guias = Guia::elegiveisParaAntecipacao($tenantId);

        $solicitacoesComRegistro = Antecipacao::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Antecipacao::STATUS_PENDENTE, Antecipacao::STATUS_GERADA])
            ->pluck('solicitacao_origem_id')
            ->all();

        return $guias
            ->whereNotIn('solicitacao_id', $solicitacoesComRegistro)
            ->groupBy('solicitacao_id')
            ->map(function ($guiasDaSolicitacao) {
                $guiaMaisAntiga = $guiasDaSolicitacao->sortBy(fn (Guia $g) => $g->antecipacaoDataAlvo())->first();
                $solicitacao = $guiaMaisAntiga->solicitacao;

                return [
                    'solicitacao_id' => (int) $guiaMaisAntiga->solicitacao_id,
                    'data_alvo' => $guiaMaisAntiga->antecipacaoDataAlvo()?->toDateString(),
                    'paciente' => $guiaMaisAntiga->paciente ? [
                        'id' => $guiaMaisAntiga->paciente->id,
                        'nome' => $guiaMaisAntiga->paciente->nome,
                    ] : null,
                    'convenio' => $guiaMaisAntiga->convenio ? [
                        'id' => $guiaMaisAntiga->convenio->id,
                        'nome' => $guiaMaisAntiga->convenio->nome,
                    ] : null,
                    'medico' => $solicitacao?->medico ? [
                        'id' => $solicitacao->medico->id,
                        'nome' => $solicitacao->medico->nome,
                        'crm' => $solicitacao->medico->crm,
                        'crm_uf' => $solicitacao->medico->crm_uf,
                    ] : null,
                    'cid_ids' => $solicitacao?->cidCadastros->pluck('id')->all() ?? [],
                    'guias' => $guiasDaSolicitacao->values()->map(fn (Guia $guia) => [
                        'guia_id' => $guia->id,
                        'numero_guia' => $guia->numero_guia,
                        'status' => $guia->status,
                        'especialidade_id' => $guia->especialidade_id,
                        'especialidade_nome' => $guia->especialidade?->nome,
                        'profissional_id' => $guia->profissional_id,
                        'profissional_nome' => $guia->profissional?->nome,
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function listar(array $filtros, int $perPage = 20): LengthAwarePaginator
    {
        $query = Antecipacao::query()
            ->with($this->relacoesPadrao())
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->paginate($perPage);
    }

    /** @return string[] */
    private function relacoesPadrao(): array
    {
        return ['solicitacaoOrigem.paciente', 'solicitacaoOrigem.convenio', 'solicitacaoGerada', 'criadoPor'];
    }

    public function criar(array $dados): Antecipacao
    {
        return Antecipacao::create([
            'solicitacao_origem_id' => $dados['solicitacao_origem_id'],
            'itens_selecionados' => $dados['itens_selecionados'],
            'data_alvo' => $dados['data_alvo'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'status' => Antecipacao::STATUS_PENDENTE,
            'criado_por_id' => auth()->id(),
            'tenant_id' => TenantContext::get() ?? auth()->user()?->tenant_id,
        ])->load($this->relacoesPadrao());
    }

    public function atualizar(Antecipacao $antecipacao, array $dados): Antecipacao
    {
        if (array_key_exists('status', $dados)) {
            $antecipacao->status = $dados['status'];
            $antecipacao->ignorado_em = $dados['status'] === Antecipacao::STATUS_IGNORADA ? now() : null;
        }

        if (array_key_exists('observacoes', $dados)) {
            $antecipacao->observacoes = $dados['observacoes'];
        }

        $antecipacao->save();

        return $antecipacao->load($this->relacoesPadrao());
    }

    public function remover(Antecipacao $antecipacao): void
    {
        $antecipacao->delete();
    }

    public function marcarGerada(Antecipacao $antecipacao, int $solicitacaoGeradaId): Antecipacao
    {
        $antecipacao->forceFill([
            'status' => Antecipacao::STATUS_GERADA,
            'solicitacao_gerada_id' => $solicitacaoGeradaId,
            'gerado_em' => now(),
        ])->save();

        return $antecipacao->load($this->relacoesPadrao());
    }
}
