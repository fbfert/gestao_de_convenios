<?php

namespace App\Services\Relatorios;

use App\Models\AutomacaoExecucao;
use App\Models\ClinicaSyncExecucao;
use App\Models\SaudeComponente;
use App\Models\SaudeComponenteEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aba de Automações: o robô trabalhou, quanto demorou, e o que quebrou.
 *
 * ── O que "fora do ar" pode e não pode afirmar ──────────────────────────────
 *
 * O tempo fora do ar sai de `saude_componente_eventos`, que só registra MUDANÇA
 * de estado e só existe a partir do deploy desta change. Período anterior ao
 * primeiro evento de um componente volta como ausente — e não como zero. A
 * diferença importa: zero afirma "esteve no ar o tempo todo", e o que o sistema
 * sabe é "não tenho registro deste período".
 */
class RelatorioAutomacoesService extends RelatorioService
{
    /** Status que o worker devolve quando a execução não deu certo. */
    private const FALHAS = ['failed', 'uncertain', 'needs_attention'];

    private const SUCESSO = 'succeeded';

    public function aba(): string
    {
        return RelatorioAba::AUTOMACOES;
    }

    protected function kpisDeclarados(): array
    {
        return [
            'execucoes' => [
                'label' => 'Execuções',
                'formato' => self::FORMATO_INTEIRO,
                'hint' => 'Execuções encerradas no período.',
            ],
            'taxa_sucesso' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Taxa de sucesso',
                'formato' => self::FORMATO_PERCENTUAL,
            ],
            'duracao_media' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Duração média',
                'formato' => self::FORMATO_HORAS,
            ],
            'duracao_p95' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Duração no percentil 95',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Noventa e cinco por cento das execuções terminaram em menos tempo que isto.',
            ],
            'tempo_medio_fila' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Tempo médio em fila',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Do enfileiramento ao início da execução.',
            ],
            'reprocessamentos' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Reprocessamentos',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'sincronizacoes_clinica' => [
                'label' => 'Sincronizações com o clínica',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'sincronizacoes_com_erro' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Sincronizações com erro',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'horas_fora_do_ar' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Horas fora do ar',
                'formato' => self::FORMATO_HORAS,
                'hint' => 'Somadas entre os componentes monitorados. Ausente quando não há registro de estado no período.',
            ],
        ];
    }

    protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array
    {
        $totais = $this->totaisDeExecucao($filtros);
        $duracoes = $this->duracoes($filtros);
        $sync = $this->totaisDeSincronizacao($filtros);
        $encerradas = (int) $totais->encerradas;

        return [
            'execucoes' => $encerradas,
            'taxa_sucesso' => $this->percentual((int) $totais->sucesso, $encerradas),
            'duracao_media' => $this->emHoras($this->media($duracoes)),
            'duracao_p95' => $this->emHoras($this->percentil($duracoes, 95)),
            'tempo_medio_fila' => $this->emHoras($this->tempoMedioEmFila($filtros)),
            'reprocessamentos' => (int) $totais->reprocessamentos,
            'sincronizacoes_clinica' => (int) $sync->total,
            'sincronizacoes_com_erro' => (int) $sync->com_erro,
            'horas_fora_do_ar' => $this->emHoras($this->segundosForaDoAr($filtros)),
        ];
    }

    protected function series(RelatorioFiltros $filtros): array
    {
        return [
            $this->serieExecucoesNoTempo($filtros),
            $this->seriePorOperacao($filtros),
            $this->serieParetoDeErros($filtros),
            $this->serieHistogramaDeDuracao($filtros),
            $this->serieSincronizacaoClinica($filtros),
        ];
    }

    protected function tabelas(RelatorioFiltros $filtros): array
    {
        return [
            $this->tabelaPorOperacao($filtros),
            $this->tabelaPorErro($filtros),
            $this->tabelaDeComponentes($filtros),
        ];
    }

    /**
     * Nenhum dos três filtros vale aqui.
     *
     * A execução aponta para o item da solicitação ou para a guia, não para
     * convênio, especialidade ou profissional — e o tempo fora do ar de um
     * componente não tem especialidade nenhuma. Devolver a lista vazia é o que
     * faz a tela dizer que o filtro não foi aplicado, em vez de o usuário
     * supor que foi.
     */
    protected function filtrosAplicados(RelatorioFiltros $filtros): array
    {
        return [];
    }

    // ── KPIs ────────────────────────────────────────────────────────────────

    private function totaisDeExecucao(RelatorioFiltros $filtros): object
    {
        $falhas = self::FALHAS;
        $interrogacoes = implode(', ', array_fill(0, count($falhas), '?'));

        return $this->execucoesEncerradas($filtros)
            ->selectRaw(
                'COUNT(*) as encerradas,'
                .' SUM(CASE WHEN automacao_execucoes.status = ? THEN 1 ELSE 0 END) as sucesso,'
                ." SUM(CASE WHEN automacao_execucoes.status IN ({$interrogacoes}) THEN 1 ELSE 0 END) as erro,"
                .' SUM(CASE WHEN automacao_execucoes.parent_id IS NOT NULL THEN 1 ELSE 0 END) as reprocessamentos',
                [self::SUCESSO, ...$falhas],
            )
            ->first();
    }

    /**
     * Durações em segundos, uma por execução encerrada.
     *
     * Volta a lista, e não um `AVG`, pelo percentil 95: nem SQLite nem MariaDB
     * têm função de percentil que valha nos dois, e a alternativa por função de
     * janela muda de sintaxe entre as versões que rodam aqui. O corte e a
     * aritmética de data continuam no banco.
     *
     * @return float[]
     */
    private function duracoes(RelatorioFiltros $filtros): array
    {
        $duracao = RelatorioSql::diferencaEmSegundos('automacao_execucoes.started_at', 'automacao_execucoes.finished_at');

        return $this->execucoesEncerradas($filtros)
            ->whereNotNull('automacao_execucoes.started_at')
            ->selectRaw("{$duracao} as segundos")
            ->pluck('segundos')
            ->map(fn ($segundos) => (float) $segundos)
            ->filter(fn (float $segundos) => $segundos >= 0)
            ->values()
            ->all();
    }

    private function tempoMedioEmFila(RelatorioFiltros $filtros): ?float
    {
        $espera = RelatorioSql::diferencaEmSegundos('automacao_execucoes.queued_at', 'automacao_execucoes.started_at');

        $media = $this->execucoesEncerradas($filtros)
            ->whereNotNull('automacao_execucoes.queued_at')
            ->whereNotNull('automacao_execucoes.started_at')
            ->selectRaw("AVG({$espera}) as segundos")
            ->value('segundos');

        return $media === null ? null : (float) $media;
    }

    private function totaisDeSincronizacao(RelatorioFiltros $filtros): object
    {
        return $this->naClinica(ClinicaSyncExecucao::query(), $filtros)
            ->whereBetween('clinica_sync_execucoes.iniciado_em', $this->janela($filtros))
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN clinica_sync_execucoes.status = ? THEN 1 ELSE 0 END) as com_erro',
                ['error'],
            )
            ->first();
    }

    // ── Tempo fora do ar ────────────────────────────────────────────────────

    /**
     * Segundos somados em que os componentes monitorados estiveram fora.
     *
     * `null` quando não há registro de estado no período — e não zero. Ver o
     * cabeçalho desta classe.
     */
    private function segundosForaDoAr(RelatorioFiltros $filtros): ?float
    {
        $janelas = $this->janelasForaDoAr($filtros);

        return $janelas === null ? null : array_sum(array_column($janelas, 'segundos'));
    }

    /**
     * Quanto cada componente ficou fora dentro do período.
     *
     * O algoritmo é um varrer de eventos, e não uma consulta agregada: o estado
     * vigente no INÍCIO do período é o último evento ANTERIOR a ele, e uma queda
     * que não terminou se estende até o fim do período. Nenhuma das duas coisas
     * sai de um `GROUP BY` — as duas dependem da ordem dos eventos.
     *
     * @return array<int, array{componente: string, segundos: float, quedas: int}>|null
     */
    private function janelasForaDoAr(RelatorioFiltros $filtros): ?array
    {
        $componentes = $this->naClinica(SaudeComponente::query(), $filtros)->get();

        if ($componentes->isEmpty()) {
            return null;
        }

        $inicio = $filtros->periodo->inicio();
        $fim = $filtros->periodo->fim();

        $eventos = $this->naClinica(SaudeComponenteEvento::query(), $filtros)
            ->where('saude_componente_eventos.ocorrido_em', '<=', $fim)
            ->orderBy('saude_componente_eventos.ocorrido_em')
            ->orderBy('saude_componente_eventos.id')
            ->get()
            ->groupBy('saude_componente_id');

        if ($eventos->isEmpty()) {
            return null;
        }

        $resultado = [];

        foreach ($componentes as $componente) {
            /** @var Collection $doComponente */
            $doComponente = $eventos->get($componente->id, collect());

            if ($doComponente->isEmpty()) {
                continue;
            }

            $segundos = 0.0;
            $quedas = 0;
            $estado = null;
            $desde = null;

            foreach ($doComponente as $evento) {
                $quando = $evento->ocorrido_em->greaterThan($inicio) ? $evento->ocorrido_em : $inicio;

                if ($estado === SaudeComponente::ESTADO_FORA && $desde !== null) {
                    $segundos += max(0, $quando->getTimestamp() - $desde->getTimestamp());
                }

                $estado = $evento->estado;
                $desde = $quando;

                if ($estado === SaudeComponente::ESTADO_FORA && $evento->ocorrido_em->greaterThanOrEqualTo($inicio)) {
                    $quedas++;
                }
            }

            // Queda que não terminou: conta até o fim do período.
            if ($estado === SaudeComponente::ESTADO_FORA && $desde !== null) {
                $segundos += max(0, $fim->getTimestamp() - $desde->getTimestamp());
            }

            $resultado[] = [
                'componente' => $componente->nome,
                'segundos' => $segundos,
                'quedas' => $quedas,
            ];
        }

        return $resultado === [] ? null : $resultado;
    }

    // ── Séries ──────────────────────────────────────────────────────────────

    private function serieExecucoesNoTempo(RelatorioFiltros $filtros): array
    {
        $balde = RelatorioSql::truncarData('automacao_execucoes.finished_at', $filtros->periodo->granularidade);
        $falhas = self::FALHAS;
        $interrogacoes = implode(', ', array_fill(0, count($falhas), '?'));

        $linhas = $this->execucoesEncerradas($filtros)
            ->selectRaw("{$balde} as balde")
            ->selectRaw(
                'SUM(CASE WHEN automacao_execucoes.status = ? THEN 1 ELSE 0 END) as ok,'
                ." SUM(CASE WHEN automacao_execucoes.status IN ({$interrogacoes}) THEN 1 ELSE 0 END) as erro",
                [self::SUCESSO, ...$falhas],
            )
            ->groupByRaw($balde)
            ->get();

        return $this->serie(
            'execucoes_por_dia',
            'Execuções com e sem sucesso',
            'linha',
            $filtros,
            $linhas->mapWithKeys(fn ($linha) => [$linha->balde => [
                'ok' => (int) $linha->ok,
                'erro' => (int) $linha->erro,
            ]])->all(),
            ['ok' => 'Sucesso', 'erro' => 'Erro'],
        );
    }

    private function seriePorOperacao(RelatorioFiltros $filtros): array
    {
        $linhas = $this->execucoesEncerradas($filtros)
            ->selectRaw('automacao_execucoes.operacao as operacao, COUNT(*) as total')
            ->groupBy('automacao_execucoes.operacao')
            ->orderByDesc('total')
            ->get();

        return $this->serieCategorica(
            'execucoes_por_operacao',
            'Execuções por operação',
            'barras',
            $linhas->map(fn ($linha) => ['x' => $linha->operacao, 'total' => (int) $linha->total])->all(),
            ['total' => 'Execuções'],
        );
    }

    private function serieParetoDeErros(RelatorioFiltros $filtros): array
    {
        $linhas = $this->errosPorCodigo($filtros);

        return $this->serieCategorica(
            'pareto_de_erros',
            'Erros por código',
            'barras',
            $linhas->map(fn ($linha) => [
                'x' => $linha->erro_codigo ?: 'Sem código',
                'total' => (int) $linha->total,
            ])->all(),
            ['total' => 'Falhas'],
        );
    }

    /**
     * Histograma de duração em faixas fixas.
     *
     * As faixas são desenhadas em PHP sobre a lista de durações que os KPIs já
     * precisam calcular — repetir a consulta para agrupar em SQL não pouparia
     * nada e traria uma segunda definição das mesmas faixas.
     */
    private function serieHistogramaDeDuracao(RelatorioFiltros $filtros): array
    {
        $faixas = [
            ['rotulo' => 'até 30s', 'ate' => 30],
            ['rotulo' => '30s a 2min', 'ate' => 120],
            ['rotulo' => '2 a 5min', 'ate' => 300],
            ['rotulo' => '5 a 15min', 'ate' => 900],
            ['rotulo' => 'acima de 15min', 'ate' => null],
        ];

        $duracoes = $this->duracoes($filtros);

        if ($duracoes === []) {
            return $this->serieCategorica('duracao_das_execucoes', 'Duração das execuções', 'barras', [], ['total' => 'Execuções']);
        }

        $pontos = [];

        foreach ($faixas as $indice => $faixa) {
            $piso = $indice === 0 ? -1 : $faixas[$indice - 1]['ate'];

            $total = count(array_filter(
                $duracoes,
                fn (float $segundos) => $segundos > $piso && ($faixa['ate'] === null || $segundos <= $faixa['ate']),
            ));

            $pontos[] = ['x' => $faixa['rotulo'], 'total' => $total];
        }

        return $this->serieCategorica('duracao_das_execucoes', 'Duração das execuções', 'barras', $pontos, ['total' => 'Execuções']);
    }

    private function serieSincronizacaoClinica(RelatorioFiltros $filtros): array
    {
        $balde = RelatorioSql::truncarData('clinica_sync_execucoes.iniciado_em', $filtros->periodo->granularidade);

        $linhas = $this->naClinica(ClinicaSyncExecucao::query(), $filtros)
            ->whereBetween('clinica_sync_execucoes.iniciado_em', $this->janela($filtros))
            ->selectRaw("{$balde} as balde")
            ->selectRaw(
                'SUM(CASE WHEN clinica_sync_execucoes.status = ? THEN 1 ELSE 0 END) as ok,'
                .' SUM(CASE WHEN clinica_sync_execucoes.status = ? THEN 1 ELSE 0 END) as erro',
                ['ok', 'error'],
            )
            ->groupByRaw($balde)
            ->get();

        return $this->serie(
            'sincronizacao_clinica',
            'Sincronização com o clínica',
            'linha',
            $filtros,
            $linhas->mapWithKeys(fn ($linha) => [$linha->balde => [
                'ok' => (int) $linha->ok,
                'erro' => (int) $linha->erro,
            ]])->all(),
            ['ok' => 'Sucesso', 'erro' => 'Erro'],
        );
    }

    // ── Tabelas ─────────────────────────────────────────────────────────────

    private function tabelaPorOperacao(RelatorioFiltros $filtros): array
    {
        $duracao = RelatorioSql::diferencaEmSegundos('automacao_execucoes.started_at', 'automacao_execucoes.finished_at');
        $falhas = self::FALHAS;
        $interrogacoes = implode(', ', array_fill(0, count($falhas), '?'));

        $linhas = $this->execucoesEncerradas($filtros)
            ->selectRaw('automacao_execucoes.operacao as operacao, COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN automacao_execucoes.status = ? THEN 1 ELSE 0 END) as sucesso,'
                ." SUM(CASE WHEN automacao_execucoes.status IN ({$interrogacoes}) THEN 1 ELSE 0 END) as erro",
                [self::SUCESSO, ...$falhas],
            )
            ->selectRaw("AVG(CASE WHEN automacao_execucoes.started_at IS NOT NULL THEN {$duracao} END) as segundos")
            ->groupBy('automacao_execucoes.operacao')
            ->orderByDesc('total')
            ->get();

        return $this->tabela(
            'por_operacao',
            'Por operação',
            [
                'operacao' => 'Operação',
                'total' => 'Execuções',
                'sucesso' => 'Sucesso',
                'erro' => 'Erro',
                'taxa_sucesso' => '% sucesso',
                'duracao_media' => 'Duração média (h)',
            ],
            $linhas->map(fn ($linha) => [
                'operacao' => $linha->operacao,
                'total' => (int) $linha->total,
                'sucesso' => (int) $linha->sucesso,
                'erro' => (int) $linha->erro,
                'taxa_sucesso' => $this->percentual((int) $linha->sucesso, (int) $linha->total),
                'duracao_media' => $this->emHoras($linha->segundos === null ? null : (float) $linha->segundos),
                // A tabela da tela transforma `href` em link: é o caminho de
                // "vi o número, quero ver as execuções por trás dele".
                'href' => '/automacoes?operacao='.rawurlencode($linha->operacao),
            ])->all(),
            [
                'total' => self::FORMATO_INTEIRO,
                'sucesso' => self::FORMATO_INTEIRO,
                'erro' => self::FORMATO_INTEIRO,
                'taxa_sucesso' => self::FORMATO_PERCENTUAL,
                'duracao_media' => self::FORMATO_HORAS,
            ],
        );
    }

    private function tabelaPorErro(RelatorioFiltros $filtros): array
    {
        $linhas = $this->errosPorCodigo($filtros, limite: null);

        return $this->tabela(
            'por_erro',
            'Por código de erro',
            ['erro_codigo' => 'Código', 'total' => 'Ocorrências'],
            $linhas->map(fn ($linha) => [
                'erro_codigo' => $linha->erro_codigo ?: 'Sem código',
                'total' => (int) $linha->total,
                'href' => '/automacoes?status=failed',
            ])->all(),
            ['total' => self::FORMATO_INTEIRO],
        );
    }

    private function tabelaDeComponentes(RelatorioFiltros $filtros): array
    {
        $janelas = $this->janelasForaDoAr($filtros) ?? [];

        usort($janelas, fn (array $a, array $b) => $b['segundos'] <=> $a['segundos']);

        return $this->tabela(
            'componentes_fora_do_ar',
            'Componentes fora do ar',
            ['componente' => 'Componente', 'quedas' => 'Quedas', 'horas' => 'Horas fora'],
            array_map(fn (array $linha) => [
                'componente' => $linha['componente'],
                'quedas' => $linha['quedas'],
                'horas' => $this->emHoras($linha['segundos']),
            ], $janelas),
            ['quedas' => self::FORMATO_INTEIRO, 'horas' => self::FORMATO_HORAS],
        );
    }

    // ── Consultas de base ───────────────────────────────────────────────────

    /**
     * Execuções ENCERRADAS no período.
     *
     * Por `finished_at`, e não por `queued_at`: o relatório mede trabalho
     * concluído, e uma execução enfileirada em setembro que só terminou em
     * outubro tem duração de outubro. Execução ainda em curso fica de fora dos
     * dois períodos até terminar — é o preço de não contar duração pela metade.
     */
    private function execucoesEncerradas(RelatorioFiltros $filtros): Builder
    {
        return $this->naClinica(AutomacaoExecucao::query(), $filtros)
            ->whereNotNull('automacao_execucoes.finished_at')
            ->whereBetween('automacao_execucoes.finished_at', $this->janela($filtros));
    }

    private function errosPorCodigo(RelatorioFiltros $filtros, ?int $limite = 10): Collection
    {
        return $this->execucoesEncerradas($filtros)
            ->whereIn('automacao_execucoes.status', self::FALHAS)
            ->selectRaw('automacao_execucoes.erro_codigo as erro_codigo, COUNT(*) as total')
            ->groupBy('automacao_execucoes.erro_codigo')
            ->orderByDesc('total')
            ->when($limite, fn (Builder $query, int $n) => $query->limit($n))
            ->get();
    }

    /** @param float[] $valores */
    private function media(array $valores): ?float
    {
        return $valores === [] ? null : array_sum($valores) / count($valores);
    }

    /**
     * Percentil pelo método do índice mais próximo, que é o que a leitura
     * operacional espera: "95% terminaram em menos que isto".
     *
     * @param  float[]  $valores
     */
    private function percentil(array $valores, int $percentil): ?float
    {
        if ($valores === []) {
            return null;
        }

        sort($valores);

        $indice = (int) ceil($percentil / 100 * count($valores)) - 1;

        return $valores[max(0, min($indice, count($valores) - 1))];
    }

    /** @return array{0: string, 1: string} */
    private function janela(RelatorioFiltros $filtros): array
    {
        return [
            $filtros->periodo->inicio()->format('Y-m-d H:i:s'),
            $filtros->periodo->fim()->format('Y-m-d H:i:s'),
        ];
    }
}
