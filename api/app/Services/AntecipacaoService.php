<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\Guia;
use App\Models\Solicitacao;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Antecipação manual: gerar as guias do próximo ciclo, replicando itens de
 * uma solicitação já em andamento — NA MESMA solicitação, como renovação
 * (`SolicitacaoItem::renovacao_de_item_id`), não como uma solicitação nova.
 * É o mesmo mecanismo que "Adicionar sessões" já usa (`SolicitacaoService::
 * adicionarItem()`): pra convênio manual a guia nasce na hora; pra Unimed RDA
 * o item fica pronto pra alguém clicar "Enviar para Unimed" depois — nunca
 * dispara a automação sozinho.
 *
 * Duas fontes convivem na tela /antecipacoes:
 *
 *   - "Elegíveis" — calculada ao vivo, sem tabela própria (ver
 *     Guia::elegiveisParaAntecipacao, a mesma lista que alimenta o alerta
 *     AntecipacaoDevida).
 *   - Histórico — registros `Antecipacao` persistidos, um por acionamento
 *     manual (da própria tela, do botão em Solicitações, ou do alerta), com
 *     status gerada/ignorada.
 */
class AntecipacaoService
{
    public function __construct(
        private readonly SolicitacaoService $solicitacoes
    ) {
    }

    /**
     * Guias elegíveis agrupadas por solicitação de origem, excluindo as que
     * já têm QUALQUER registro de Antecipacao (gerada ou ignorada) — uma vez
     * revisada, a solicitação sai da fila.
     */
    public function listarElegiveis(int $tenantId): array
    {
        $guias = Guia::elegiveisParaAntecipacao($tenantId);

        $solicitacoesComRegistro = Antecipacao::query()
            ->where('tenant_id', $tenantId)
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
        return ['solicitacaoOrigem.paciente', 'solicitacaoOrigem.convenio', 'criadoPor'];
    }

    /**
     * Gera de fato: para cada par especialidade+profissional escolhido, cria
     * um `SolicitacaoItem` novo encadeado (`renovacao_de_item_id`) ao item já
     * existente da mesma solicitação — mesmo caminho de
     * `SolicitacaoService::adicionarItem()` que "Adicionar sessões" usa. O
     * registro `Antecipacao` nasce já como histórico do que foi gerado
     * (`itens_selecionados` grava também `item_gerado_id`/`guia_gerada_id`).
     */
    public function criar(array $dados): Antecipacao
    {
        $solicitacao = Solicitacao::query()->findOrFail($dados['solicitacao_origem_id']);
        $itensGerados = [];

        foreach ($dados['itens_selecionados'] as $escolha) {
            $itemOrigem = $solicitacao->itens()
                ->where('especialidade_id', $escolha['especialidade_id'])
                ->where('profissional_id', $escolha['profissional_id'])
                ->latest('id')
                ->first();

            if (! $itemOrigem) {
                throw ValidationException::withMessages([
                    'itens_selecionados' => ['Um dos itens escolhidos não pertence a esta solicitação.'],
                ]);
            }

            $novoItem = $this->solicitacoes->adicionarItem($solicitacao, [
                'especialidade_id' => $escolha['especialidade_id'],
                'profissional_id' => $escolha['profissional_id'],
                'renovacao_de_item_id' => $itemOrigem->id,
            ]);

            $itensGerados[] = [
                'especialidade_id' => $novoItem->especialidade_id,
                'profissional_id' => $novoItem->profissional_id,
                'item_gerado_id' => $novoItem->id,
                'guia_gerada_id' => $novoItem->guia?->id,
            ];
        }

        return Antecipacao::create([
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => $itensGerados,
            'data_alvo' => $dados['data_alvo'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'status' => Antecipacao::STATUS_GERADA,
            'gerado_em' => now(),
            'criado_por_id' => auth()->id(),
            'tenant_id' => TenantContext::get() ?? auth()->user()?->tenant_id,
        ])->load($this->relacoesPadrao());
    }

    /** Dispensa uma solicitação elegível sem gerar nada — some da fila sem virar histórico de geração. */
    public function ignorar(array $dados): Antecipacao
    {
        return Antecipacao::create([
            'solicitacao_origem_id' => $dados['solicitacao_origem_id'],
            'data_alvo' => $dados['data_alvo'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'status' => Antecipacao::STATUS_IGNORADA,
            'ignorado_em' => now(),
            'criado_por_id' => auth()->id(),
            'tenant_id' => TenantContext::get() ?? auth()->user()?->tenant_id,
        ])->load($this->relacoesPadrao());
    }

    public function atualizar(Antecipacao $antecipacao, array $dados): Antecipacao
    {
        if (array_key_exists('observacoes', $dados)) {
            $antecipacao->observacoes = $dados['observacoes'];
            $antecipacao->save();
        }

        return $antecipacao->load($this->relacoesPadrao());
    }

    public function remover(Antecipacao $antecipacao): void
    {
        $antecipacao->delete();
    }
}
