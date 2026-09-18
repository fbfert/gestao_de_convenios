<?php

namespace App\Services\Relatorios;

use App\Models\AnaliticoUnimedLinha;
use App\Models\AnaliticoUnimedLote;
use App\Models\ConciliacaoFinanceira;
use App\Models\Lancamento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aba Financeira: o que a clínica executou, o que a operadora reconheceu, e a
 * diferença entre os dois.
 *
 * ── Duas origens que não se misturam ────────────────────────────────────────
 *
 * **Executado** é da clínica: sessão realizada × valor vigente da tabela na
 * DATA DA SESSÃO. É o que a clínica entende que fez.
 *
 * **Apresentado, pago e glosado** são da operadora, e saem dos lotes de
 * analítico importados. "Apresentado" não é coluna: vem de
 * `total_pago + total_glosado` do lote, porque é isso que a operadora
 * processou. Quem procurar um campo `valor_apresentado` não vai achar, e
 * derivá-lo da tabela de valores misturaria o que a clínica cobrou com o que a
 * operadora reconheceu — dois números diferentes que passariam a parecer um só.
 *
 * Sem lote no período, os três voltam ausentes, e não zero: zero afirmaria que
 * a operadora não pagou nada, quando o que houve foi ninguém ter importado o
 * analítico ainda.
 *
 * ── Dinheiro ────────────────────────────────────────────────────────────────
 *
 * Tudo em centavos inteiros. `decimal:2` do Eloquent volta string; somar string
 * em PHP e depois em JS é como se perde centavo. A formatação é da tela.
 */
class RelatorioFinanceiroService extends RelatorioService
{
    public function aba(): string
    {
        return RelatorioAba::FINANCEIRO;
    }

    protected function kpisDeclarados(): array
    {
        return [
            'valor_executado' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Valor executado',
                'formato' => self::FORMATO_MOEDA,
                'hint' => 'Sessões realizadas no período pelo valor vigente na data de cada sessão.',
            ],
            'valor_apresentado' => [
                'label' => 'Valor apresentado',
                'formato' => self::FORMATO_MOEDA,
                'hint' => 'Pago mais glosado nos lotes de analítico importados no período.',
            ],
            'valor_pago' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Valor pago',
                'formato' => self::FORMATO_MOEDA,
            ],
            'valor_glosado' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Glosa',
                'formato' => self::FORMATO_MOEDA,
            ],
            'taxa_glosa' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => '% de glosa',
                'formato' => self::FORMATO_PERCENTUAL,
                'hint' => 'Glosado sobre apresentado.',
            ],
            'conciliacoes_pendentes' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Conciliações pendentes',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'conciliacoes_revisadas' => [
                'label' => 'Conciliações conferidas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'conciliacoes_pagas' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Conciliações pagas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'repasse_estimado' => [
                'label' => 'Repasse estimado',
                'formato' => self::FORMATO_MOEDA,
                'hint' => 'Executado × percentual de repasse cadastrado em cada profissional.',
            ],
        ];
    }

    protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array
    {
        $lote = $this->totaisDoAnalitico($filtros);
        $conciliacoes = $this->totaisDeConciliacao($filtros);
        $executado = $this->valorExecutado($filtros);

        return [
            'valor_executado' => $executado,
            'valor_apresentado' => $lote->apresentado,
            'valor_pago' => $lote->pago,
            'valor_glosado' => $lote->glosado,
            'taxa_glosa' => $lote->apresentado === null
                ? null
                : $this->percentual($lote->glosado, $lote->apresentado),
            'conciliacoes_pendentes' => (int) $conciliacoes->pendentes,
            'conciliacoes_revisadas' => (int) $conciliacoes->revisadas,
            'conciliacoes_pagas' => (int) $conciliacoes->pagas,
            'repasse_estimado' => $this->repasseEstimado($filtros),
        ];
    }

    protected function series(RelatorioFiltros $filtros): array
    {
        return [
            $this->serieExecutadoVersusPago($filtros),
            $this->serieGlosaPorMotivo($filtros),
            $this->serieRepassePorProfissional($filtros),
            $this->serieSituacaoDasConciliacoes($filtros),
        ];
    }

    protected function tabelas(RelatorioFiltros $filtros): array
    {
        return [
            $this->tabelaPorProfissional($filtros),
            $this->tabelaPorConvenio($filtros),
            $this->tabelaDeGlosa($filtros),
        ];
    }

    /**
     * O analítico não guarda convênio nem especialidade por linha, então os
     * números da operadora não respondem a esses filtros. O executado responde
     * aos três — e é por isso que a resposta lista o que foi usado.
     */
    protected function filtrosAplicados(RelatorioFiltros $filtros): array
    {
        return $filtros->informados();
    }

    // ── Números da operadora ────────────────────────────────────────────────

    /**
     * Pago e glosado dos lotes importados no período.
     *
     * Por `importado_em`, e não pela competência do arquivo: é a data que a
     * clínica reconhece ("o analítico de setembro entrou em outubro"), e é a
     * única que o lote guarda de forma confiável.
     */
    private function totaisDoAnalitico(RelatorioFiltros $filtros): object
    {
        $linha = $this->naClinica(AnaliticoUnimedLote::query(), $filtros)
            ->whereBetween('analitico_unimed_lotes.importado_em', $this->janela($filtros))
            ->selectRaw('COUNT(*) as lotes, SUM(total_pago) as pago, SUM(total_glosado) as glosado')
            ->first();

        if ((int) $linha->lotes === 0) {
            return (object) ['apresentado' => null, 'pago' => null, 'glosado' => null];
        }

        $pago = $this->emCentavos($linha->pago ?? 0);
        $glosado = $this->emCentavos($linha->glosado ?? 0);

        return (object) [
            'apresentado' => $pago + $glosado,
            'pago' => $pago,
            'glosado' => $glosado,
        ];
    }

    private function totaisDeConciliacao(RelatorioFiltros $filtros): object
    {
        return $this->conciliacoesDoPeriodo($filtros)
            ->selectRaw(
                'SUM(CASE WHEN conciliacoes_financeiras.status = ? THEN 1 ELSE 0 END) as pendentes,'
                .' SUM(CASE WHEN conciliacoes_financeiras.status = ? THEN 1 ELSE 0 END) as revisadas,'
                .' SUM(CASE WHEN conciliacoes_financeiras.status = ? THEN 1 ELSE 0 END) as pagas',
                ['pending', 'reviewed', 'paid'],
            )
            ->first();
    }

    // ── Valor executado ─────────────────────────────────────────────────────

    private function valorExecutado(RelatorioFiltros $filtros): int
    {
        return (int) $this->sessoesRealizadas($filtros)
            ->selectRaw('COALESCE(SUM('.$this->valorDaSessao().'), 0) as total')
            ->value('total');
    }

    private function repasseEstimado(RelatorioFiltros $filtros): int
    {
        return (int) $this->sessoesRealizadas($filtros)
            ->join('profissionais as prof', 'prof.id', '=', 'lancamentos.profissional_id')
            ->selectRaw(
                'COALESCE(SUM(ROUND(('.$this->valorDaSessao().') * COALESCE(prof.percentual_repasse, 0) / 100)), 0) as total'
            )
            ->value('total');
    }

    /**
     * O valor da sessão, em centavos, pela cascata de `tabela_valores`.
     *
     * A cascata é a mesma do `TabelaValoresService` — exato (convênio +
     * especialidade + profissional), depois só especialidade, depois só
     * convênio —, mas escrita em SQL de propósito: o serviço resolve UMA guia
     * por chamada e usa `today()` como data de vigência. Aqui são milhares de
     * sessões, cada uma com a sua data; chamar o serviço por linha seria uma
     * consulta por sessão, e usar `today()` faria o relatório de meses atrás
     * ser calculado com a tabela de preços de hoje.
     *
     * O `ORDER BY` reproduz a prioridade: a linha mais específica primeiro,
     * desempatada pela vigência mais recente. `(coluna IS NOT NULL)` vale 1 ou
     * 0 nos dois bancos.
     */
    private function valorDaSessao(): string
    {
        return <<<'SQL'
            (SELECT ROUND(tv.valor * 100)
               FROM tabela_valores tv
              WHERE tv.tenant_id = guia_da_sessao.tenant_id
                AND tv.convenio_id = guia_da_sessao.convenio_id
                AND tv.vigente_desde <= lancamentos.data_sessao
                AND (tv.vigente_ate IS NULL OR tv.vigente_ate >= lancamentos.data_sessao)
                AND (tv.especialidade_id IS NULL OR tv.especialidade_id = guia_da_sessao.especialidade_id)
                AND (tv.profissional_id IS NULL OR tv.profissional_id = lancamentos.profissional_id)
              ORDER BY (tv.especialidade_id IS NOT NULL) DESC,
                       (tv.profissional_id IS NOT NULL) DESC,
                       tv.vigente_desde DESC
              LIMIT 1)
            SQL;
    }

    // ── Séries ──────────────────────────────────────────────────────────────

    private function serieExecutadoVersusPago(RelatorioFiltros $filtros): array
    {
        $baldeSessao = RelatorioSql::truncarData('lancamentos.data_sessao', $filtros->periodo->granularidade);

        $executado = $this->sessoesRealizadas($filtros)
            ->selectRaw("{$baldeSessao} as balde, COALESCE(SUM(".$this->valorDaSessao().'), 0) as total')
            ->groupByRaw($baldeSessao)
            ->pluck('total', 'balde');

        $baldeLote = RelatorioSql::truncarData('analitico_unimed_lotes.importado_em', $filtros->periodo->granularidade);

        $pago = $this->naClinica(AnaliticoUnimedLote::query(), $filtros)
            ->whereBetween('analitico_unimed_lotes.importado_em', $this->janela($filtros))
            ->selectRaw("{$baldeLote} as balde, SUM(total_pago) as total")
            ->groupByRaw($baldeLote)
            ->pluck('total', 'balde');

        $porBalde = [];

        foreach ($executado as $balde => $total) {
            $porBalde[$balde]['executado'] = (int) $total;
        }

        foreach ($pago as $balde => $total) {
            $porBalde[$balde]['pago'] = $this->emCentavos($total);
        }

        return $this->serie(
            'executado_x_pago',
            'Executado × pago',
            'linha',
            $filtros,
            $porBalde,
            ['executado' => 'Executado', 'pago' => 'Pago pelo convênio'],
        );
    }

    /**
     * Pareto dos motivos de glosa.
     *
     * `motivo` é texto livre vindo da planilha da operadora, então o
     * agrupamento é pelo texto tal como veio — normalizar aqui inventaria
     * categoria que a operadora não usou.
     */
    private function serieGlosaPorMotivo(RelatorioFiltros $filtros): array
    {
        $linhas = $this->linhasDeGlosa($filtros)
            ->selectRaw('analitico_unimed_linhas.motivo as motivo, SUM(valor_normalizado) as total')
            ->groupBy('analitico_unimed_linhas.motivo')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return $this->serieCategorica(
            'glosa_por_motivo',
            'Glosa por motivo',
            'barras',
            $linhas->map(fn ($linha) => [
                'x' => $linha->motivo ?: 'Sem motivo informado',
                'total' => $this->emCentavos($linha->total),
            ])->all(),
            ['total' => 'Glosado'],
        );
    }

    private function serieRepassePorProfissional(RelatorioFiltros $filtros): array
    {
        $linhas = $this->sessoesRealizadas($filtros)
            ->join('profissionais as prof', 'prof.id', '=', 'lancamentos.profissional_id')
            ->selectRaw('prof.nome as nome')
            ->selectRaw(
                'COALESCE(SUM(ROUND(('.$this->valorDaSessao().') * COALESCE(prof.percentual_repasse, 0) / 100)), 0) as total'
            )
            ->groupBy('prof.nome')
            ->orderByDesc('total')
            ->get();

        return $this->serieCategorica(
            'repasse_por_profissional',
            'Repasse estimado por profissional',
            'barras',
            $linhas->map(fn ($linha) => ['x' => $linha->nome, 'total' => (int) $linha->total])->all(),
            ['total' => 'Repasse'],
        );
    }

    private function serieSituacaoDasConciliacoes(RelatorioFiltros $filtros): array
    {
        $porStatus = $this->conciliacoesDoPeriodo($filtros)
            ->selectRaw('conciliacoes_financeiras.status as status, COUNT(*) as total')
            ->groupBy('conciliacoes_financeiras.status')
            ->pluck('total', 'status');

        $rotulos = ['pending' => 'Pendentes', 'reviewed' => 'Conferidas', 'paid' => 'Pagas'];
        $pontos = [];

        foreach ($rotulos as $status => $rotulo) {
            if (($porStatus[$status] ?? 0) > 0) {
                $pontos[] = ['x' => $rotulo, 'total' => (int) $porStatus[$status]];
            }
        }

        return $this->serieCategorica(
            'situacao_das_conciliacoes',
            'Situação das conciliações',
            'pizza',
            $pontos,
            ['total' => 'Conciliações'],
        );
    }

    // ── Tabelas ─────────────────────────────────────────────────────────────

    /**
     * Executado e repasse por profissional.
     *
     * Sem apresentado, pago nem glosa por linha: o analítico da operadora chega
     * agregado por lote, sem convênio nem profissional. Uma coluna "pago por
     * profissional" só poderia ser rateada por estimativa, e rateio apresentado
     * como medição é o tipo de número que alguém usa para pagar alguém.
     */
    private function tabelaPorProfissional(RelatorioFiltros $filtros): array
    {
        $linhas = $this->sessoesRealizadas($filtros)
            ->join('profissionais as prof', 'prof.id', '=', 'lancamentos.profissional_id')
            ->selectRaw('prof.nome as nome, COUNT(*) as sessoes')
            ->selectRaw('COALESCE(SUM('.$this->valorDaSessao().'), 0) as executado')
            ->selectRaw('COALESCE(prof.percentual_repasse, 0) as percentual')
            ->selectRaw(
                'COALESCE(SUM(ROUND(('.$this->valorDaSessao().') * COALESCE(prof.percentual_repasse, 0) / 100)), 0) as repasse'
            )
            ->groupBy('prof.nome', 'prof.percentual_repasse')
            ->orderByDesc('executado')
            ->get();

        return $this->tabela(
            'por_profissional',
            'Por profissional',
            [
                'nome' => 'Profissional',
                'sessoes' => 'Sessões',
                'executado' => 'Executado',
                'percentual' => '% repasse',
                'repasse' => 'Repasse estimado',
            ],
            $linhas->map(fn ($linha) => [
                'nome' => $linha->nome,
                'sessoes' => (int) $linha->sessoes,
                'executado' => (int) $linha->executado,
                'percentual' => (float) $linha->percentual,
                'repasse' => (int) $linha->repasse,
            ])->all(),
            [
                'sessoes' => self::FORMATO_INTEIRO,
                'executado' => self::FORMATO_MOEDA,
                'percentual' => self::FORMATO_PERCENTUAL,
                'repasse' => self::FORMATO_MOEDA,
            ],
        );
    }

    private function tabelaPorConvenio(RelatorioFiltros $filtros): array
    {
        $linhas = $this->sessoesRealizadas($filtros)
            ->join('convenios', 'convenios.id', '=', 'guia_da_sessao.convenio_id')
            ->selectRaw('convenios.nome as nome, COUNT(*) as sessoes')
            ->selectRaw('COALESCE(SUM('.$this->valorDaSessao().'), 0) as executado')
            ->groupBy('convenios.nome')
            ->orderByDesc('executado')
            ->get();

        return $this->tabela(
            'por_convenio',
            'Por convênio',
            ['nome' => 'Convênio', 'sessoes' => 'Sessões', 'executado' => 'Executado'],
            $linhas->map(fn ($linha) => [
                'nome' => $linha->nome,
                'sessoes' => (int) $linha->sessoes,
                'executado' => (int) $linha->executado,
            ])->all(),
            ['sessoes' => self::FORMATO_INTEIRO, 'executado' => self::FORMATO_MOEDA],
        );
    }

    private function tabelaDeGlosa(RelatorioFiltros $filtros): array
    {
        $linhas = $this->linhasDeGlosa($filtros)
            ->selectRaw('analitico_unimed_linhas.motivo as motivo, COUNT(*) as ocorrencias, SUM(valor_normalizado) as total')
            ->groupBy('analitico_unimed_linhas.motivo')
            ->orderByDesc('total')
            ->get();

        return $this->tabela(
            'glosa_por_motivo',
            'Glosa por motivo',
            ['motivo' => 'Motivo', 'ocorrencias' => 'Ocorrências', 'total' => 'Glosado'],
            $linhas->map(fn ($linha) => [
                'motivo' => $linha->motivo ?: 'Sem motivo informado',
                'ocorrencias' => (int) $linha->ocorrencias,
                'total' => $this->emCentavos($linha->total),
            ])->all(),
            ['ocorrencias' => self::FORMATO_INTEIRO, 'total' => self::FORMATO_MOEDA],
        );
    }

    // ── Consultas de base ───────────────────────────────────────────────────

    /**
     * Sessões realizadas no período, já com a guia em join.
     *
     * A guia entra sempre, e não só quando há filtro: é dela que saem convênio e
     * especialidade, que a cascata de valores precisa para achar o preço.
     */
    private function sessoesRealizadas(RelatorioFiltros $filtros): Builder
    {
        return $this->naClinica(Lancamento::query(), $filtros)
            ->join('guias as guia_da_sessao', 'guia_da_sessao.id', '=', 'lancamentos.guia_id')
            ->where('lancamentos.status', 'completed')
            ->whereBetween('lancamentos.data_sessao', $this->janelaDeDatas($filtros))
            ->when($filtros->convenioId, fn (Builder $q, int $id) => $q->where('guia_da_sessao.convenio_id', $id))
            ->when($filtros->especialidadeId, fn (Builder $q, int $id) => $q->where('guia_da_sessao.especialidade_id', $id))
            ->when($filtros->profissionalId, fn (Builder $q, int $id) => $q->where('lancamentos.profissional_id', $id));
    }

    private function conciliacoesDoPeriodo(RelatorioFiltros $filtros): Builder
    {
        return $this->naClinica(ConciliacaoFinanceira::query(), $filtros)
            ->whereBetween('conciliacoes_financeiras.created_at', $this->janela($filtros))
            ->when($filtros->profissionalId, fn (Builder $q, int $id) => $q->where('conciliacoes_financeiras.profissional_id', $id))
            ->when(
                $filtros->convenioId !== null || $filtros->especialidadeId !== null,
                fn (Builder $q) => $q->whereExists(fn ($sub) => $sub
                    ->from('guias')
                    ->whereColumn('guias.id', 'conciliacoes_financeiras.guia_id')
                    ->when($filtros->convenioId, fn ($s, $id) => $s->where('guias.convenio_id', $id))
                    ->when($filtros->especialidadeId, fn ($s, $id) => $s->where('guias.especialidade_id', $id))),
            );
    }

    /** As linhas de glosa dos lotes importados no período. */
    private function linhasDeGlosa(RelatorioFiltros $filtros): Builder
    {
        return $this->naClinica(AnaliticoUnimedLinha::query(), $filtros)
            ->join('analitico_unimed_lotes as lote', 'lote.id', '=', 'analitico_unimed_linhas.analitico_unimed_lote_id')
            ->where('analitico_unimed_linhas.origem', 'glosa')
            ->whereBetween('lote.importado_em', $this->janela($filtros));
    }

    /** @return array{0: string, 1: string} */
    private function janela(RelatorioFiltros $filtros): array
    {
        return [
            $filtros->periodo->inicio()->format('Y-m-d H:i:s'),
            $filtros->periodo->fim()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function janelaDeDatas(RelatorioFiltros $filtros): array
    {
        return [$filtros->periodo->de->toDateString(), $filtros->periodo->ate->toDateString()];
    }
}
