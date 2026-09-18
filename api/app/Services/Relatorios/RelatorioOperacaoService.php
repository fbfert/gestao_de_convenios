<?php

namespace App\Services\Relatorios;

use App\Models\Antecipacao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\Solicitacao;
use App\Support\GuiaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aba de Operação: o que entrou, o que foi decidido, e quanto tempo levou.
 *
 * ── A regra que manda nesta aba ─────────────────────────────────────────────
 *
 * Aprovação, negação e tempo de decisão saem de `guia_status_historico`, pela
 * data em que a TRANSIÇÃO aconteceu — nunca de `guias.created_at`. Uma guia
 * criada em agosto e negada em setembro é negação de setembro; contar pela
 * criação joga o número no mês errado e faz a série mentir exatamente onde ela
 * deveria informar. É a mesma regra do card de guias do painel
 * (`DashboardGuiasCardService`), e o `COUNT(DISTINCT guia_id)` também vem de lá:
 * uma guia negada duas vezes no mesmo dia é uma guia negada, não duas.
 *
 * "Solicitações criadas" e "guias geradas", ao contrário, são eventos de
 * criação e saem de `created_at` mesmo — é o que a palavra "criadas" quer dizer.
 */
class RelatorioOperacaoService extends RelatorioService
{
    /** Transições que contam como decisão da operadora sobre a guia. */
    private const DECISOES = [GuiaStatus::APPROVED, GuiaStatus::FINALIZED, GuiaStatus::DENIED];

    private const FAVORAVEIS = [GuiaStatus::APPROVED, GuiaStatus::FINALIZED];

    public function aba(): string
    {
        return RelatorioAba::OPERACAO;
    }

    protected function kpisDeclarados(): array
    {
        return [
            'solicitacoes_criadas' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Solicitações criadas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'guias_geradas' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Guias geradas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'taxa_aprovacao' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Taxa de aprovação',
                'formato' => self::FORMATO_PERCENTUAL,
                'hint' => 'Guias autorizadas ou aprovadas sobre o total de guias decididas no período, pela data da decisão.',
            ],
            'taxa_negacao' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Taxa de negação',
                'formato' => self::FORMATO_PERCENTUAL,
                'hint' => 'Guias negadas sobre o total de guias decididas no período, pela data da decisão.',
            ],
            'tempo_medio_decisao' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Tempo médio até a decisão',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Da entrada em análise até a decisão.',
            ],
            'tempo_mediano_decisao' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Tempo mediano até a decisão',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Metade das guias foi decidida em menos tempo que isto.',
            ],
            'tempo_medio_finalizacao' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Tempo médio até a aprovação',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Da autorização até a aprovação, com senha e validade registradas.',
            ],
            'sessoes_realizadas' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Sessões realizadas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'sessoes_faltas' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Faltas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'sessoes_canceladas' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Sessões canceladas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'senhas_vencendo' => [
                'label' => 'Senhas a vencer',
                'formato' => self::FORMATO_INTEIRO,
                'hint' => 'Dentro da janela configurada para a clínica. É um retrato de agora, não do período.',
            ],
            'antecipacoes_geradas' => [
                'label' => 'Antecipações geradas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'antecipacoes_ignoradas' => [
                'label' => 'Antecipações dispensadas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'taxa_dispensa_antecipacao' => [
                'label' => 'Dispensa de antecipação',
                'formato' => self::FORMATO_PERCENTUAL,
                'hint' => 'Quanto das antecipações decididas no período foi dispensado pelo operador.',
            ],
        ];
    }

    protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array
    {
        $decisoes = $this->totaisDeDecisao($filtros);
        $duracoes = $this->duracoesAteDecisao($filtros);
        $sessoes = $this->totaisDeSessao($filtros);
        $antecipacoes = $this->totaisDeAntecipacao($filtros);

        $decididas = (int) $decisoes->decididas;
        $antecipacoesDecididas = $antecipacoes->geradas + $antecipacoes->ignoradas;

        return [
            'solicitacoes_criadas' => $this->contarSolicitacoes($filtros),
            'guias_geradas' => $this->contarGuias($filtros),
            'taxa_aprovacao' => $this->percentual((int) $decisoes->aprovadas, $decididas),
            'taxa_negacao' => $this->percentual((int) $decisoes->negadas, $decididas),
            'tempo_medio_decisao' => $this->emHoras($this->media($duracoes)),
            'tempo_mediano_decisao' => $this->emHoras($this->mediana($duracoes)),
            'tempo_medio_finalizacao' => $this->emHoras($this->media($this->duracoesAteFinalizacao($filtros))),
            'sessoes_realizadas' => (int) $sessoes->realizadas,
            'sessoes_faltas' => (int) $sessoes->faltas,
            'sessoes_canceladas' => (int) $sessoes->canceladas,
            // Retrato do agora: não existe "senhas a vencer do mês passado".
            'senhas_vencendo' => $ehComparacao ? null : $this->contarSenhasVencendo($filtros),
            'antecipacoes_geradas' => $antecipacoes->geradas,
            'antecipacoes_ignoradas' => $antecipacoes->ignoradas,
            'taxa_dispensa_antecipacao' => $this->percentual($antecipacoes->ignoradas, $antecipacoesDecididas),
        ];
    }

    protected function series(RelatorioFiltros $filtros): array
    {
        return [
            $this->serieDecisoesNoTempo($filtros),
            $this->serieTempoDeDecisao($filtros),
            $this->serieFunil($filtros),
            $this->serieSolicitacoesPorEspecialidade($filtros),
            $this->serieSessoesPorProfissional($filtros),
            $this->serieGuiasPorConvenio($filtros),
        ];
    }

    protected function tabelas(RelatorioFiltros $filtros): array
    {
        return [
            $this->tabelaPor($filtros, 'por_especialidade', 'Por especialidade', 'Especialidade', 'especialidades', 'especialidade_id'),
            $this->tabelaPor($filtros, 'por_profissional', 'Por profissional', 'Profissional', 'profissionais', 'profissional_id'),
            $this->tabelaPor($filtros, 'por_convenio', 'Por convênio', 'Convênio', 'convenios', 'convenio_id'),
        ];
    }

    /** Todos os três filtros fazem sentido aqui: guia e sessão carregam as três colunas. */
    protected function filtrosAplicados(RelatorioFiltros $filtros): array
    {
        return $filtros->informados();
    }

    // ── KPIs ────────────────────────────────────────────────────────────────

    private function contarSolicitacoes(RelatorioFiltros $filtros): int
    {
        return $this->comFiltros(Solicitacao::query(), $filtros, 'solicitacoes')
            ->whereBetween('solicitacoes.created_at', $this->janela($filtros))
            ->count();
    }

    private function contarGuias(RelatorioFiltros $filtros): int
    {
        return $this->comFiltros(Guia::query(), $filtros, 'guias')
            ->whereBetween('guias.created_at', $this->janela($filtros))
            ->count();
    }

    /**
     * Aprovadas, negadas e decididas no período — pela data da transição.
     *
     * Uma varredura só, com `COUNT(DISTINCT ... CASE)`: três consultas separadas
     * responderiam o mesmo lendo a mesma tabela três vezes.
     */
    private function totaisDeDecisao(RelatorioFiltros $filtros): object
    {
        return $this->historicoDecisoes($filtros)
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN guia_status_historico.para IN (?, ?) THEN guia_status_historico.guia_id END) as aprovadas,'
                .' COUNT(DISTINCT CASE WHEN guia_status_historico.para = ? THEN guia_status_historico.guia_id END) as negadas,'
                .' COUNT(DISTINCT guia_status_historico.guia_id) as decididas',
                [...self::FAVORAVEIS, GuiaStatus::DENIED],
            )
            ->first();
    }

    private function totaisDeSessao(RelatorioFiltros $filtros): object
    {
        return $this->comFiltrosDeSessao(Lancamento::query(), $filtros)
            ->whereBetween('lancamentos.data_sessao', $this->janelaDeDatas($filtros))
            ->selectRaw(
                'SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as realizadas,'
                .' SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as faltas,'
                .' SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as canceladas',
                ['completed', 'missed', 'canceled'],
            )
            ->first();
    }

    /**
     * Antecipações geradas e dispensadas no período.
     *
     * Cada uma pela sua própria data (`gerado_em` e `ignorado_em`), e não por
     * `created_at`: a entrada nasce na fila de elegíveis e pode ficar dias lá
     * antes de alguém decidir — é a decisão que o relatório mede.
     */
    private function totaisDeAntecipacao(RelatorioFiltros $filtros): object
    {
        [$de, $ate] = $this->janela($filtros);

        $linha = $this->comFiltrosDeAntecipacao(Antecipacao::query(), $filtros)
            ->selectRaw(
                'SUM(CASE WHEN antecipacoes.gerado_em BETWEEN ? AND ? THEN 1 ELSE 0 END) as geradas,'
                .' SUM(CASE WHEN antecipacoes.ignorado_em BETWEEN ? AND ? THEN 1 ELSE 0 END) as ignoradas',
                [$de, $ate, $de, $ate],
            )
            ->first();

        return (object) [
            'geradas' => (int) $linha->geradas,
            'ignoradas' => (int) $linha->ignoradas,
        ];
    }

    /**
     * Guias cuja senha vence dentro da janela configurada PARA CADA CLÍNICA.
     *
     * O prazo sai de `configuracoes_globais.senha_alerta_dias`, nunca de um
     * número em código (é regra de negócio configurável, ADR-03). O `join` com a
     * configuração, em vez de uma data-limite calculada em PHP, é o que permite
     * responder a visão "todas as clínicas" numa consulta só — cada tenant tem o
     * seu prazo.
     *
     * `leftJoin` com `COALESCE`, e não `join`: a linha de configuração nasce sob
     * demanda, e uma clínica que nunca abriu a tela de configurações não tem a
     * dela. Com junção interna essa clínica sumiria do número inteiro — e um
     * zero que parece "nenhuma senha vencendo" é pior do que um erro.
     */
    private function contarSenhasVencendo(RelatorioFiltros $filtros): int
    {
        $diferenca = RelatorioSql::diferencaEmDias('?', 'guias.validade_senha');
        $hoje = today()->toDateString();
        $padrao = ConfiguracaoGlobal::SENHA_ALERTA_DIAS_PADRAO;

        return $this->comFiltros(Guia::query(), $filtros, 'guias')
            ->leftJoin('configuracoes_globais as cfg', 'cfg.tenant_id', '=', 'guias.tenant_id')
            ->whereNotNull('guias.validade_senha')
            ->whereRaw("{$diferenca} >= 0", [$hoje])
            ->whereRaw("{$diferenca} <= COALESCE(cfg.senha_alerta_dias, {$padrao})", [$hoje])
            ->count();
    }

    // ── Tempos ──────────────────────────────────────────────────────────────

    /**
     * Quanto cada guia decidida no período levou desde a entrada em análise.
     *
     * @return float[] durações em segundos
     */
    private function duracoesAteDecisao(RelatorioFiltros $filtros): array
    {
        return $this->duracoesEntreTransicoes($filtros, [GuiaStatus::UNDER_REVIEW], self::DECISOES);
    }

    /** @return float[] */
    private function duracoesAteFinalizacao(RelatorioFiltros $filtros): array
    {
        return $this->duracoesEntreTransicoes($filtros, [GuiaStatus::APPROVED], [GuiaStatus::FINALIZED]);
    }

    /**
     * UMA linha por guia, com a duração já calculada pelo banco; média e mediana
     * saem em PHP sobre esse vetor.
     *
     * A mediana é o motivo de a lista voltar em vez de um `AVG`: nem MariaDB nem
     * SQLite têm `MEDIAN()`, e a alternativa — função de janela — muda de
     * sintaxe entre as versões que rodam aqui. A agregação pesada (join, filtro,
     * aritmética de data) continua no banco, e o volume é limitado pelo teto de
     * 366 dias do período: uma linha por guia decidida, não uma por transição.
     *
     * `MIN` da duração, e não a duração da primeira decisão: o par que interessa
     * é a entrada em análise MAIS RECENTE antes da decisão MAIS ANTIGA, e é
     * justamente esse par que produz o menor intervalo entre os candidatos. Uma
     * guia que volta para análise e é decidida de novo mede, assim, o ciclo mais
     * curto — e não a soma de dois ciclos com a espera no meio.
     *
     * @param  string[]  $partida  status cuja entrada marca o início
     * @param  string[]  $chegada  status cuja entrada, dentro do período, marca o fim
     * @return float[]
     */
    private function duracoesEntreTransicoes(RelatorioFiltros $filtros, array $partida, array $chegada): array
    {
        $duracao = RelatorioSql::diferencaEmSegundos('inicio.ocorrido_em', 'guia_status_historico.ocorrido_em');

        return $this->historicoNoPeriodo($filtros, $chegada)
            ->join('guia_status_historico as inicio', function ($join) use ($partida) {
                $join->on('inicio.guia_id', '=', 'guia_status_historico.guia_id')
                    ->whereIn('inicio.para', $partida)
                    ->whereColumn('inicio.ocorrido_em', '<=', 'guia_status_historico.ocorrido_em');
            })
            ->selectRaw("MIN({$duracao}) as segundos")
            ->groupBy('guia_status_historico.guia_id')
            ->pluck('segundos')
            ->map(fn ($segundos) => (float) $segundos)
            ->filter(fn (float $segundos) => $segundos >= 0)
            ->values()
            ->all();
    }

    /** @param float[] $valores */
    private function media(array $valores): ?float
    {
        return $valores === [] ? null : array_sum($valores) / count($valores);
    }

    /** @param float[] $valores */
    private function mediana(array $valores): ?float
    {
        if ($valores === []) {
            return null;
        }

        sort($valores);
        $meio = intdiv(count($valores), 2);

        return count($valores) % 2 === 1
            ? $valores[$meio]
            : ($valores[$meio - 1] + $valores[$meio]) / 2;
    }

    // ── Séries ──────────────────────────────────────────────────────────────

    private function serieDecisoesNoTempo(RelatorioFiltros $filtros): array
    {
        $balde = RelatorioSql::truncarData('guia_status_historico.ocorrido_em', $filtros->periodo->granularidade);

        $linhas = $this->historicoNoPeriodo($filtros, [...self::DECISOES, GuiaStatus::UNDER_REVIEW])
            ->selectRaw("{$balde} as balde")
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN guia_status_historico.para IN (?, ?) THEN guia_status_historico.guia_id END) as aprovadas,'
                .' COUNT(DISTINCT CASE WHEN guia_status_historico.para = ? THEN guia_status_historico.guia_id END) as negadas,'
                .' COUNT(DISTINCT CASE WHEN guia_status_historico.para = ? THEN guia_status_historico.guia_id END) as em_analise',
                [...self::FAVORAVEIS, GuiaStatus::DENIED, GuiaStatus::UNDER_REVIEW],
            )
            ->groupByRaw($balde)
            ->get();

        return $this->serie(
            'guias_por_status',
            'Guias por status ao longo do tempo',
            'area',
            $filtros,
            $linhas->mapWithKeys(fn ($linha) => [$linha->balde => [
                'em_analise' => (int) $linha->em_analise,
                'aprovadas' => (int) $linha->aprovadas,
                'negadas' => (int) $linha->negadas,
            ]])->all(),
            ['em_analise' => 'Em análise', 'aprovadas' => 'Autorizadas', 'negadas' => 'Negadas'],
        );
    }

    private function serieTempoDeDecisao(RelatorioFiltros $filtros): array
    {
        $balde = RelatorioSql::truncarData('guia_status_historico.ocorrido_em', $filtros->periodo->granularidade);
        $duracao = RelatorioSql::diferencaEmSegundos('inicio.ocorrido_em', 'guia_status_historico.ocorrido_em');

        $linhas = $this->historicoNoPeriodo($filtros, self::DECISOES)
            ->join('guia_status_historico as inicio', function ($join) {
                $join->on('inicio.guia_id', '=', 'guia_status_historico.guia_id')
                    ->where('inicio.para', GuiaStatus::UNDER_REVIEW)
                    ->whereColumn('inicio.ocorrido_em', '<=', 'guia_status_historico.ocorrido_em');
            })
            ->selectRaw("{$balde} as balde")
            // MAX(inicio) dentro do grupo é a última entrada em análise antes da
            // decisão — a mesma escolha do KPI, feita aqui pelo agrupamento.
            ->selectRaw("AVG({$duracao}) as segundos")
            ->groupByRaw($balde)
            ->get();

        return $this->serie(
            'tempo_de_decisao',
            'Tempo médio até a decisão',
            'linha',
            $filtros,
            $linhas->mapWithKeys(fn ($linha) => [
                $linha->balde => ['horas' => round(((float) $linha->segundos) / 3600, 1)],
            ])->all(),
            ['horas' => 'Horas'],
        );
    }

    /**
     * O funil não é uma série temporal: são três degraus mais as negadas ao
     * lado, todos contados pela transição dentro do período.
     */
    private function serieFunil(RelatorioFiltros $filtros): array
    {
        $porStatus = $this->historicoNoPeriodo($filtros, [...self::DECISOES, GuiaStatus::UNDER_REVIEW])
            ->selectRaw('guia_status_historico.para as para, COUNT(DISTINCT guia_status_historico.guia_id) as total')
            ->groupBy('guia_status_historico.para')
            ->pluck('total', 'para');

        $degraus = [
            GuiaStatus::UNDER_REVIEW => 'Em análise',
            GuiaStatus::APPROVED => 'Autorizadas',
            GuiaStatus::FINALIZED => 'Aprovadas',
            GuiaStatus::DENIED => 'Negadas',
        ];

        $pontos = [];

        foreach ($degraus as $status => $rotulo) {
            $pontos[] = ['x' => $rotulo, 'total' => (int) ($porStatus[$status] ?? 0)];
        }

        return $this->serieCategorica('funil_de_guias', 'Funil de guias', 'funil', $pontos, ['total' => 'Guias']);
    }

    private function serieSolicitacoesPorEspecialidade(RelatorioFiltros $filtros): array
    {
        $linhas = $this->comFiltros(Solicitacao::query(), $filtros, 'solicitacoes')
            ->join('especialidades', 'especialidades.id', '=', 'solicitacoes.especialidade_id')
            ->whereBetween('solicitacoes.created_at', $this->janela($filtros))
            ->selectRaw('especialidades.nome as nome, COUNT(*) as total')
            ->groupBy('especialidades.nome')
            ->orderByDesc('total')
            ->get();

        return $this->serieCategorica(
            'solicitacoes_por_especialidade',
            'Solicitações por especialidade',
            'barras',
            $linhas->map(fn ($linha) => ['x' => $linha->nome, 'total' => (int) $linha->total])->all(),
            ['total' => 'Solicitações'],
        );
    }

    private function serieSessoesPorProfissional(RelatorioFiltros $filtros): array
    {
        $linhas = $this->comFiltrosDeSessao(Lancamento::query(), $filtros)
            ->join('profissionais', 'profissionais.id', '=', 'lancamentos.profissional_id')
            ->whereBetween('lancamentos.data_sessao', $this->janelaDeDatas($filtros))
            ->selectRaw('profissionais.nome as nome')
            ->selectRaw(
                'SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as realizadas,'
                .' SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as faltas',
                ['completed', 'missed'],
            )
            ->groupBy('profissionais.nome')
            ->orderByDesc('realizadas')
            ->get();

        return $this->serieCategorica(
            'sessoes_por_profissional',
            'Sessões por profissional',
            'barras',
            $linhas->map(fn ($linha) => [
                'x' => $linha->nome,
                'realizadas' => (int) $linha->realizadas,
                'faltas' => (int) $linha->faltas,
            ])->all(),
            ['realizadas' => 'Realizadas', 'faltas' => 'Faltas'],
        );
    }

    private function serieGuiasPorConvenio(RelatorioFiltros $filtros): array
    {
        $linhas = $this->comFiltros(Guia::query(), $filtros, 'guias')
            ->join('convenios', 'convenios.id', '=', 'guias.convenio_id')
            ->whereBetween('guias.created_at', $this->janela($filtros))
            ->selectRaw('convenios.nome as nome, COUNT(*) as total')
            ->groupBy('convenios.nome')
            ->orderByDesc('total')
            ->get();

        return $this->serieCategorica(
            'guias_por_convenio',
            'Guias por convênio',
            'barras',
            $linhas->map(fn ($linha) => ['x' => $linha->nome, 'total' => (int) $linha->total])->all(),
            ['total' => 'Guias'],
        );
    }

    // ── Tabelas ─────────────────────────────────────────────────────────────

    /**
     * A mesma tabela recortada por especialidade, profissional ou convênio.
     *
     * As três respondem a mesma pergunta sobre eixos diferentes, então são a
     * mesma consulta com a coluna de agrupamento trocada — escrever três
     * variantes convidaria as definições a divergirem no primeiro ajuste.
     */
    private function tabelaPor(
        RelatorioFiltros $filtros,
        string $key,
        string $label,
        string $rotuloDoEixo,
        string $tabelaDoEixo,
        string $coluna,
    ): array {
        $colunaDaGuia = $coluna;

        $solicitacoes = $this->comFiltros(Solicitacao::query(), $filtros, 'solicitacoes')
            ->whereBetween('solicitacoes.created_at', $this->janela($filtros))
            ->selectRaw("solicitacoes.{$colunaDaGuia} as eixo_id, COUNT(*) as total")
            ->groupBy("solicitacoes.{$colunaDaGuia}")
            ->pluck('total', 'eixo_id');

        $guias = $this->comFiltros(Guia::query(), $filtros, 'guias')
            ->whereBetween('guias.created_at', $this->janela($filtros))
            ->selectRaw("guias.{$colunaDaGuia} as eixo_id, COUNT(*) as total")
            ->groupBy("guias.{$colunaDaGuia}")
            ->pluck('total', 'eixo_id');

        $decisoes = $this->historicoDecisoes($filtros)
            ->selectRaw("guias.{$colunaDaGuia} as eixo_id")
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN guia_status_historico.para IN (?, ?) THEN guia_status_historico.guia_id END) as aprovadas,'
                .' COUNT(DISTINCT CASE WHEN guia_status_historico.para = ? THEN guia_status_historico.guia_id END) as negadas,'
                .' COUNT(DISTINCT guia_status_historico.guia_id) as decididas',
                [...self::FAVORAVEIS, GuiaStatus::DENIED],
            )
            ->groupBy("guias.{$colunaDaGuia}")
            ->get()
            ->keyBy('eixo_id');

        $sessoes = $this->comFiltrosDeSessao(Lancamento::query(), $filtros)
            ->join('guias as guia_da_sessao', 'guia_da_sessao.id', '=', 'lancamentos.guia_id')
            ->whereBetween('lancamentos.data_sessao', $this->janelaDeDatas($filtros))
            ->selectRaw(
                "guia_da_sessao.{$colunaDaGuia} as eixo_id,"
                .' SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as realizadas,'
                .' SUM(CASE WHEN lancamentos.status = ? THEN 1 ELSE 0 END) as faltas',
                ['completed', 'missed'],
            )
            ->groupBy("guia_da_sessao.{$colunaDaGuia}")
            ->get()
            ->keyBy('eixo_id');

        $nomes = $this->nomesDoEixo($filtros, $tabelaDoEixo, [
            ...$solicitacoes->keys()->all(),
            ...$guias->keys()->all(),
            ...$decisoes->keys()->all(),
            ...$sessoes->keys()->all(),
        ]);

        $linhas = [];

        foreach ($nomes as $id => $nome) {
            $decisao = $decisoes[$id] ?? null;
            $sessao = $sessoes[$id] ?? null;
            $decididas = (int) ($decisao->decididas ?? 0);

            $linhas[] = [
                'nome' => $nome,
                'solicitacoes' => (int) ($solicitacoes[$id] ?? 0),
                'guias' => (int) ($guias[$id] ?? 0),
                'aprovadas' => (int) ($decisao->aprovadas ?? 0),
                'negadas' => (int) ($decisao->negadas ?? 0),
                'taxa_aprovacao' => $this->percentual((int) ($decisao->aprovadas ?? 0), $decididas),
                'sessoes' => (int) ($sessao->realizadas ?? 0),
                'faltas' => (int) ($sessao->faltas ?? 0),
            ];
        }

        usort($linhas, fn (array $a, array $b) => $b['guias'] <=> $a['guias'] ?: strcmp($a['nome'], $b['nome']));

        return $this->tabela(
            $key,
            $label,
            [
                'nome' => $rotuloDoEixo,
                'solicitacoes' => 'Solicitações',
                'guias' => 'Guias',
                'aprovadas' => 'Autorizadas',
                'negadas' => 'Negadas',
                'taxa_aprovacao' => '% aprovação',
                'sessoes' => 'Sessões',
                'faltas' => 'Faltas',
            ],
            $linhas,
            [
                'solicitacoes' => self::FORMATO_INTEIRO,
                'guias' => self::FORMATO_INTEIRO,
                'aprovadas' => self::FORMATO_INTEIRO,
                'negadas' => self::FORMATO_INTEIRO,
                'taxa_aprovacao' => self::FORMATO_PERCENTUAL,
                'sessoes' => self::FORMATO_INTEIRO,
                'faltas' => self::FORMATO_INTEIRO,
            ],
        );
    }

    /**
     * @param  array<int, int|string|null>  $ids
     * @return array<int, string>
     */
    private function nomesDoEixo(RelatorioFiltros $filtros, string $tabela, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));

        if ($ids === []) {
            return [];
        }

        return DB::table($tabela)
            ->whereIn('id', $ids)
            ->when(! $filtros->todasAsClinicas(), fn ($query) => $query->where('tenant_id', $filtros->tenantId))
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    // ── Consultas de base ───────────────────────────────────────────────────

    /** Histórico de decisões do período, já com `guias` em join para os filtros. */
    private function historicoDecisoes(RelatorioFiltros $filtros): Builder
    {
        return $this->historicoNoPeriodo($filtros, self::DECISOES);
    }

    /**
     * @param  string[]  $paraStatus
     */
    private function historicoNoPeriodo(RelatorioFiltros $filtros, array $paraStatus): Builder
    {
        $query = $this->naClinica(GuiaStatusHistorico::query(), $filtros)
            ->join('guias', 'guias.id', '=', 'guia_status_historico.guia_id')
            ->whereIn('guia_status_historico.para', $paraStatus)
            ->whereBetween('guia_status_historico.ocorrido_em', $this->janela($filtros));

        return $this->aplicarFiltrosDeGuia($query, $filtros, 'guias');
    }

    /** @param Builder<*> $query */
    private function comFiltros(Builder $query, RelatorioFiltros $filtros, string $tabela): Builder
    {
        return $this->aplicarFiltrosDeGuia($this->naClinica($query, $filtros), $filtros, $tabela);
    }

    /**
     * Sessão não tem convênio nem especialidade — os dois vivem na guia, então
     * o filtro exige o join. `profissional_id` a sessão tem: é o profissional
     * que ATENDEU, que pode não ser o da guia.
     *
     * @param  Builder<*>  $query
     */
    private function comFiltrosDeSessao(Builder $query, RelatorioFiltros $filtros): Builder
    {
        $query = $this->naClinica($query, $filtros);

        if ($filtros->convenioId !== null || $filtros->especialidadeId !== null) {
            $query->whereExists(fn ($sub) => $sub
                ->from('guias')
                ->whereColumn('guias.id', 'lancamentos.guia_id')
                ->when($filtros->convenioId, fn ($q, $id) => $q->where('guias.convenio_id', $id))
                ->when($filtros->especialidadeId, fn ($q, $id) => $q->where('guias.especialidade_id', $id)));
        }

        if ($filtros->profissionalId !== null) {
            $query->where('lancamentos.profissional_id', $filtros->profissionalId);
        }

        return $query;
    }

    /**
     * A antecipação aponta para a solicitação de origem, e é de lá que saem
     * convênio, especialidade e profissional.
     *
     * @param  Builder<*>  $query
     */
    private function comFiltrosDeAntecipacao(Builder $query, RelatorioFiltros $filtros): Builder
    {
        $query = $this->naClinica($query, $filtros);

        if ($filtros->informados() === []) {
            return $query;
        }

        return $query->whereExists(fn ($sub) => $sub
            ->from('solicitacoes')
            ->whereColumn('solicitacoes.id', 'antecipacoes.solicitacao_origem_id')
            ->when($filtros->convenioId, fn ($q, $id) => $q->where('solicitacoes.convenio_id', $id))
            ->when($filtros->especialidadeId, fn ($q, $id) => $q->where('solicitacoes.especialidade_id', $id))
            ->when($filtros->profissionalId, fn ($q, $id) => $q->where('solicitacoes.profissional_id', $id)));
    }

    /** @param Builder<*> $query */
    private function aplicarFiltrosDeGuia(Builder $query, RelatorioFiltros $filtros, string $tabela): Builder
    {
        return $query
            ->when($filtros->convenioId, fn (Builder $q, int $id) => $q->where("{$tabela}.convenio_id", $id))
            ->when($filtros->especialidadeId, fn (Builder $q, int $id) => $q->where("{$tabela}.especialidade_id", $id))
            ->when($filtros->profissionalId, fn (Builder $q, int $id) => $q->where("{$tabela}.profissional_id", $id));
    }

    /** @return array{0: string, 1: string} instantes, para coluna `datetime` */
    private function janela(RelatorioFiltros $filtros): array
    {
        return [
            $filtros->periodo->inicio()->format('Y-m-d H:i:s'),
            $filtros->periodo->fim()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array{0: string, 1: string} datas, para coluna `date` */
    private function janelaDeDatas(RelatorioFiltros $filtros): array
    {
        return [$filtros->periodo->de->toDateString(), $filtros->periodo->ate->toDateString()];
    }
}
