<?php

namespace App\Services\Relatorios;

use App\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Base das quatro abas: contrato de resposta, escopo de clínica, comparação com
 * o período anterior e cache.
 *
 * ── Sobre o `withoutGlobalScope` ────────────────────────────────────────────
 *
 * Este é o ÚNICO lugar do sistema que derruba o `TenantScope` de propósito, e
 * ele precisa continuar sendo. O escopo global é o que garante que nenhuma
 * consulta do sistema enxergue clínica alheia por esquecimento; abrir exceção
 * espalhada pelo código transforma essa garantia em convenção, e convenção
 * vaza.
 *
 * A visão "todas as clínicas" do super admin não tem como sair sem derrubar o
 * escopo — o `TenantContext` carrega o tenant do usuário autenticado, e é ele
 * que o escopo aplica. Concentrar a derrubada aqui torna o teste de isolamento
 * possível de escrever ("usuário comum não vê número de outro tenant, nem
 * passando `tenant_id`") e a revisão óbvia: `grep withoutGlobalScope` tem que
 * continuar respondendo só este arquivo.
 *
 * O escopo é derrubado mesmo quando há UMA clínica escolhida, e o `where` é
 * reaplicado à mão. Não é descuido: o super admin que escolhe outra clínica
 * tem, no contexto, o tenant DELE — manter o escopo global filtraria pela
 * clínica errada e devolveria um relatório vazio que parece um relatório
 * legítimo. Um caminho só, com o filtro sempre explícito, é mais fácil de
 * conferir do que dois caminhos que divergem só no caso raro.
 *
 * ── Sobre a comparação ──────────────────────────────────────────────────────
 *
 * A aba declara os KPIs (`kpisDeclarados`) e sabe calcular os números crus de
 * UM período (`metricas`). A base chama `metricas()` de novo no período
 * anterior quando a comparação é pedida. Assim `anterior` nasce em todo KPI
 * sem cada aba lembrar de calculá-lo — e um KPI novo não pode nascer sem
 * comparação, porque não há onde esquecê-la.
 */
abstract class RelatorioService
{
    /**
     * Cinco minutos: curto o bastante para ninguém decidir sobre número velho,
     * longo o bastante para trocar de aba e voltar não refazer a agregação.
     */
    public const TTL_SEGUNDOS = 300;

    public const FORMATO_INTEIRO = 'inteiro';

    public const FORMATO_PERCENTUAL = 'percentual';

    public const FORMATO_MOEDA = 'moeda';

    public const FORMATO_HORAS = 'horas';

    /**
     * Para onde o indicador deve ir.
     *
     * Existe porque a tela pinta a variação, e pintar exige saber o que é
     * melhora. Subir é bom em "sessões realizadas" e é ruim em "taxa de
     * negação"; sem esta declaração o front teria que adivinhar — na prática,
     * manter uma lista de exceções por chave, longe de onde o KPI é definido, e
     * que ninguém lembraria de atualizar ao criar um indicador novo.
     *
     * `NEUTRO` é o padrão, e não um palpite: há indicador que simplesmente não
     * tem lado bom (execuções, ações por dia). A tela mostra a variação sem
     * cor semântica nesses casos.
     */
    public const SENTIDO_MAIOR_MELHOR = 'maior_melhor';

    public const SENTIDO_MENOR_MELHOR = 'menor_melhor';

    public const SENTIDO_NEUTRO = 'neutro';

    /** Qual aba este serviço responde — entra na chave de cache. */
    abstract public function aba(): string;

    /**
     * Os KPIs da aba, em ordem de exibição.
     *
     * @return array<string, array{label: string, formato: string, hint?: string, sentido?: string}>
     */
    abstract protected function kpisDeclarados(): array;

    /**
     * Os números crus de UM período, por chave de KPI.
     *
     * `null` significa "sem base de cálculo", e é diferente de zero: taxa de
     * aprovação sem nenhuma guia decidida não é 0%, é uma pergunta sem resposta.
     * A tela mostra "—" e não desenha variação.
     *
     * `$ehComparacao` distingue a passada do período anterior. Alguns KPIs são
     * um retrato do AGORA — senhas a vencer, por exemplo —, e não têm valor
     * "anterior" nenhum: recalculá-los sobre o período passado devolveria o
     * mesmo número de hoje e a tela desenharia 0% de variação, afirmando uma
     * estabilidade que ninguém mediu. Esses devolvem `null` na comparação.
     *
     * @return array<string, int|float|null>
     */
    abstract protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array;

    /** @return array<int, array<string, mixed>> */
    abstract protected function series(RelatorioFiltros $filtros): array;

    /** @return array<int, array<string, mixed>> */
    abstract protected function tabelas(RelatorioFiltros $filtros): array;

    /**
     * Quais filtros a aba de fato usou.
     *
     * @return array<string, mixed>
     */
    abstract protected function filtrosAplicados(RelatorioFiltros $filtros): array;

    /** @return array<string, mixed> o contrato completo da resposta */
    public function montar(RelatorioFiltros $filtros): array
    {
        $chave = $this->chaveDeCache($filtros);

        // `has()` antes do `remember()` só para informar a origem. O valor em si
        // continua vindo do `remember()`: consultar duas vezes o cache é barato,
        // e devolver `cache: false` num acerto seria pior do que não informar
        // nada — a tela usa esse campo para explicar por que o número não mudou.
        $veioDoCache = Cache::has($chave);

        $conteudo = Cache::remember(
            $chave,
            self::TTL_SEGUNDOS,
            fn () => $this->conteudo($filtros),
        );

        return [...$conteudo, 'cache' => $veioDoCache];
    }

    public function chaveDeCache(RelatorioFiltros $filtros): string
    {
        return 'relatorios:'.$filtros->escopoDaClinica().':'.$this->aba().':'.$filtros->hash();
    }

    /**
     * Prende a consulta à clínica do relatório.
     *
     * Todo `Builder` de model com `BelongsToTenant` usado por um serviço de
     * relatório passa por aqui — inclusive no caminho de uma clínica só.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    protected function naClinica(Builder $query, RelatorioFiltros $filtros): Builder
    {
        $query->withoutGlobalScope(TenantScope::class);

        if (! $filtros->todasAsClinicas()) {
            $query->where($query->getModel()->getTable().'.tenant_id', $filtros->tenantId);
        }

        return $query;
    }

    // ── Apoio para as abas ──────────────────────────────────────────────────

    /**
     * Uma série pronta, com os baldes vazios preenchidos.
     *
     * Dia sem sessão é zero sessões, e o gráfico precisa desenhar essa queda —
     * pular o ponto faria a linha ligar os dois dias vizinhos e esconder o
     * buraco. Já uma série ONDE NADA aconteceu volta sem ponto nenhum, para a
     * tela dizer "sem dados no período" em vez de desenhar uma reta no zero,
     * que afirma algo diferente.
     *
     * @param  array<string, array<string, int|float>>  $porBalde  balde (YYYY-MM-DD) => campos
     * @param  array<string, string>  $campos  chave do campo => rótulo
     */
    protected function serie(
        string $key,
        string $label,
        string $tipo,
        RelatorioFiltros $filtros,
        array $porBalde,
        array $campos,
    ): array {
        $pontos = [];

        if ($porBalde !== []) {
            $zerados = array_fill_keys(array_keys($campos), 0);

            foreach ($this->baldesDoPeriodo($filtros->periodo) as $balde) {
                $pontos[] = ['x' => $balde, ...$zerados, ...($porBalde[$balde] ?? [])];
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'tipo' => $tipo,
            'pontos' => $pontos,
            'campos' => array_map(
                fn (string $rotulo, string $chave) => ['key' => $chave, 'label' => $rotulo],
                $campos,
                array_keys($campos),
            ),
        ];
    }

    /**
     * Série de categorias — por convênio, por especialidade, por profissional.
     *
     * Não tem balde vazio para preencher: categoria sem ocorrência simplesmente
     * não existe na série, ao contrário de um dia sem ocorrência, que existe e
     * vale zero. Inventar a categoria com zero encheria o gráfico de barras
     * nulas de cadastros que ninguém usou no período.
     *
     * @param  array<int, array<string, mixed>>  $pontos  cada um com `x` e os campos
     * @param  array<string, string>  $campos  chave do campo => rótulo
     */
    protected function serieCategorica(string $key, string $label, string $tipo, array $pontos, array $campos): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'tipo' => $tipo,
            'pontos' => $pontos,
            'campos' => array_map(
                fn (string $rotulo, string $chave) => ['key' => $chave, 'label' => $rotulo],
                $campos,
                array_keys($campos),
            ),
        ];
    }

    /**
     * Os baldes que o período cobre, na granularidade escolhida.
     *
     * @return string[] datas YYYY-MM-DD, em ordem
     */
    protected function baldesDoPeriodo(RelatorioPeriodo $periodo): array
    {
        $baldes = [];
        $cursor = $this->inicioDoBalde($periodo->de, $periodo->granularidade);

        while ($cursor->lessThanOrEqualTo($periodo->ate)) {
            $baldes[] = $cursor->toDateString();

            $cursor = match ($periodo->granularidade) {
                RelatorioPeriodo::DIA => $cursor->addDay(),
                RelatorioPeriodo::SEMANA => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $baldes;
    }

    /** Espelha RelatorioSql::truncarData, do lado do PHP. */
    protected function inicioDoBalde(CarbonImmutable $data, string $granularidade): CarbonImmutable
    {
        return match ($granularidade) {
            RelatorioPeriodo::DIA => $data->startOfDay(),
            RelatorioPeriodo::SEMANA => $data->startOfWeek(CarbonImmutable::MONDAY),
            default => $data->startOfMonth(),
        };
    }

    /**
     * Percentual com uma casa, ou `null` quando não há denominador.
     *
     * Zero por cento e "não dá para calcular" são coisas diferentes: a primeira
     * diz que nada foi aprovado, a segunda que nada foi decidido.
     */
    protected function percentual(int|float $parte, int|float $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }

    /** Segundos em horas com uma casa — o formato `horas` dos KPIs. */
    protected function emHoras(int|float|null $segundos): ?float
    {
        return $segundos === null ? null : round($segundos / 3600, 1);
    }

    /** Valor decimal do banco em centavos inteiros, que é como a API devolve dinheiro. */
    protected function emCentavos(int|float|string|null $valor): ?int
    {
        return $valor === null ? null : (int) round(((float) $valor) * 100);
    }

    /**
     * @param  array<string, string>  $colunas  chave => rótulo, no formato do contrato
     * @param  array<int, array<string, mixed>>  $linhas
     * @param  array<string, string>  $formatos  chave da coluna => formato; o padrão é texto
     */
    protected function tabela(string $key, string $label, array $colunas, array $linhas, array $formatos = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'colunas' => array_map(
                fn (string $rotulo, string $chave) => [
                    'key' => $chave,
                    'label' => $rotulo,
                    'formato' => $formatos[$chave] ?? 'texto',
                ],
                $colunas,
                array_keys($colunas),
            ),
            'linhas' => $linhas,
        ];
    }

    // ── Montagem do contrato ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function conteudo(RelatorioFiltros $filtros): array
    {
        $atuais = $this->metricas($filtros);
        $anteriores = $filtros->comparar
            ? $this->metricas($filtros->noPeriodoAnterior(), ehComparacao: true)
            : [];

        return [
            'periodo' => $filtros->periodo->toArray(),
            'comparacao' => $filtros->comparar
                ? [
                    'de' => $filtros->periodo->anterior()->de->toDateString(),
                    'ate' => $filtros->periodo->anterior()->ate->toDateString(),
                ]
                : null,
            // Só os filtros que a aba de fato usou. Um filtro que não faz sentido
            // para a aba (profissional em automações, por exemplo) some daqui em
            // vez de aparecer como se tivesse restringido alguma coisa.
            'filtros_aplicados' => $this->filtrosAplicados($filtros),
            'kpis' => $this->kpis($atuais, $anteriores, $filtros->comparar),
            'series' => $this->series($filtros),
            'tabelas' => $this->tabelas($filtros),
            'gerado_em' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int|float|null>  $atuais
     * @param  array<string, int|float|null>  $anteriores
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $atuais, array $anteriores, bool $comparar): array
    {
        $kpis = [];

        foreach ($this->kpisDeclarados() as $key => $declaracao) {
            $kpis[] = [
                'key' => $key,
                'label' => $declaracao['label'],
                'valor' => $atuais[$key] ?? null,
                // Sem comparação pedida, `anterior` é null — e não o valor de
                // agora. A tela distingue os dois para não desenhar "0%" de
                // variação onde não houve comparação nenhuma.
                'anterior' => $comparar ? ($anteriores[$key] ?? null) : null,
                'formato' => $declaracao['formato'],
                'sentido' => $declaracao['sentido'] ?? self::SENTIDO_NEUTRO,
                'hint' => $declaracao['hint'] ?? null,
            ];
        }

        return $kpis;
    }
}
