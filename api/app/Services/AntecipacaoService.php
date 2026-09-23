<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
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
    ) {}

    /**
     * Guias elegíveis agrupadas por solicitação de origem, excluindo as que
     * já têm QUALQUER registro de Antecipacao (gerada ou ignorada) — uma vez
     * revisada, a solicitação sai da fila.
     */
    /**
     * Quantas entradas a fila de listarElegiveis() teria, sem montar a fila:
     * o dashboard pergunta isso a cada 30s e não precisa de paciente, itens
     * nem solicitação carregados.
     */
    public function contarElegiveis(int $tenantId): int
    {
        $solicitacoesComRegistro = Antecipacao::query()
            ->where('tenant_id', $tenantId)
            ->pluck('solicitacao_origem_id')
            ->all();

        return Guia::solicitacaoIdsElegiveisParaAntecipacao($tenantId)
            ->diff($solicitacoesComRegistro)
            ->count();
    }

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

    /**
     * Histórico filtrado.
     *
     * `numero_guia` procura em QUALQUER guia da solicitação de origem, e não
     * só nas que a antecipação gerou. Não é frouxidão: antecipar cria itens
     * na MESMA solicitação, então a guia gerada é uma guia da origem — a
     * mesma condição cobre as duas. E cobre também o caso que importa achar,
     * o registro `ignorada`, que não gerou guia nenhuma e só é pesquisável
     * pelo número da guia que motivou o aviso.
     *
     * O período recai sobre `created_at` — quando alguém agiu —, não sobre
     * `data_alvo`, que é quando a antecipação era devida.
     */
    public function listar(array $filtros, int $perPage = 20): LengthAwarePaginator
    {
        $query = Antecipacao::query()
            ->with($this->relacoesPadrao())
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (! empty($filtros['paciente_nome'])) {
            $nome = $filtros['paciente_nome'];
            $query->whereHas('solicitacaoOrigem.paciente', fn ($origem) => $origem
                ->where('nome', 'like', '%'.$nome.'%'));
        }

        if (! empty($filtros['convenio_id'])) {
            $convenioId = (int) $filtros['convenio_id'];
            $query->whereHas('solicitacaoOrigem', fn ($origem) => $origem
                ->where('convenio_id', $convenioId));
        }

        if (! empty($filtros['numero_guia'])) {
            $numero = $filtros['numero_guia'];
            $query->whereHas('solicitacaoOrigem.guias', fn ($guias) => $guias
                ->where('numero_guia', 'like', '%'.$numero.'%'));
        }

        if (! empty($filtros['data_de'])) {
            $query->whereDate('created_at', '>=', $filtros['data_de']);
        }

        if (! empty($filtros['data_ate'])) {
            $query->whereDate('created_at', '<=', $filtros['data_ate']);
        }

        $pagina = $query->paginate($perPage);

        $this->resolverItensGerados($pagina->getCollection());

        return $pagina;
    }

    /**
     * Anexa a cada antecipação os itens gerados com a guia de cada um.
     *
     * `itens_selecionados` é um retrato do momento da geração, e nele o
     * `guia_gerada_id` nasce NULO sempre que o convênio é automatizado: ali a
     * guia não existe junto com o item — chega depois, quando a operadora
     * responde. Confiar no campo gravado deixaria essas antecipações sem guia
     * para sempre, então a resolução é pelo `item_gerado_id`, que não muda.
     *
     * Uma consulta para a página inteira, e não uma por linha: o histórico
     * pagina de 20 em 20.
     *
     * @param  iterable<int, Antecipacao>  $antecipacoes
     */
    public function resolverItensGerados(iterable $antecipacoes): void
    {
        $antecipacoes = collect($antecipacoes);

        $ids = $antecipacoes
            ->flatMap(fn (Antecipacao $a) => collect($a->itens_selecionados ?? [])->pluck('item_gerado_id'))
            ->filter()
            ->unique()
            ->all();

        $itens = $ids === []
            ? collect()
            : SolicitacaoItem::query()->whereIn('id', $ids)
                ->with(['guia', 'especialidade', 'automacaoExecucoes'])
                ->get()
                ->keyBy('id');

        foreach ($antecipacoes as $antecipacao) {
            $antecipacao->setAttribute('itens_gerados', collect($antecipacao->itens_selecionados ?? [])
                ->map(function (array $escolha) use ($itens) {
                    $item = $itens->get($escolha['item_gerado_id'] ?? null);
                    $guia = $item?->guia;

                    return [
                        'especialidade' => $item?->especialidade?->nome,
                        'item_gerado_id' => $escolha['item_gerado_id'] ?? null,
                        'guia' => $guia ? [
                            'id' => $guia->id,
                            'numero' => $guia->numero_guia,
                            'status' => $guia->status,
                        ] : null,
                        // Sem guia ainda: é o que decide se a tela pode oferecer
                        // "Enviar para a operadora" pra este item, e se já tem
                        // execução em aberto travando o reenvio.
                        'automacao_execucao_ativa' => $item
                            ? AutomacaoExecucao::ativaMaisRecente($item->automacaoExecucoes)
                            : null,
                    ];
                })
                ->all());
        }
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

        $antecipacao = Antecipacao::create([
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => $itensGerados,
            'data_alvo' => $dados['data_alvo'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'status' => Antecipacao::STATUS_GERADA,
            'gerado_em' => now(),
            'criado_por_id' => auth()->id(),
            'tenant_id' => TenantContext::get() ?? auth()->user()?->tenant_id,
        ])->load($this->relacoesPadrao());

        // A resposta do POST já sai com as guias resolvidas, para a tela não
        // precisar de uma segunda requisição só para mostrá-las.
        $this->resolverItensGerados([$antecipacao]);

        return $antecipacao;
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

    /**
     * Desfaz uma dispensa: apaga o registro `ignorada` e, com isso, devolve a
     * solicitação à fila de elegíveis — `listarElegiveis()` exclui pela
     * EXISTÊNCIA de um registro, então tirar o registro é o desfazer inteiro.
     * Se a solicitação não for mais elegível por outro motivo (guia vencida,
     * alerta ocultado na guia), ela simplesmente não reaparece; o desfazer
     * não força nada de volta.
     *
     * Só vale para `ignorada`. Desfazer uma `gerada` teria de apagar itens e
     * guias já criados, e foi exatamente por apagar o registro sem desfazer
     * os efeitos que o `DELETE /antecipacoes/{id}` genérico saiu em
     * 16/09/2026 — a rota continua inexistente (405), de propósito.
     */
    public function desfazerIgnorada(Antecipacao $antecipacao): void
    {
        if ($antecipacao->status !== Antecipacao::STATUS_IGNORADA) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível desfazer uma antecipação ignorada.'],
            ]);
        }

        $antecipacao->delete();
    }

    public function atualizar(Antecipacao $antecipacao, array $dados): Antecipacao
    {
        if (array_key_exists('observacoes', $dados)) {
            $antecipacao->observacoes = $dados['observacoes'];
            $antecipacao->save();
        }

        return $antecipacao->load($this->relacoesPadrao());
    }
}
