<?php

namespace App\Services\Relatorios;

use App\Scopes\TenantScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Base das quatro abas: contrato de resposta, escopo de clínica e cache.
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
 */
abstract class RelatorioService
{
    /**
     * Cinco minutos: curto o bastante para ninguém decidir sobre número velho,
     * longo o bastante para trocar de aba e voltar não refazer a agregação.
     */
    public const TTL_SEGUNDOS = 300;

    /** Qual aba este serviço responde — entra na chave de cache. */
    abstract public function aba(): string;

    /**
     * O miolo da aba.
     *
     * @return array{kpis: array<int, array<string, mixed>>, series: array<int, array<string, mixed>>, tabelas: array<int, array<string, mixed>>, filtros_aplicados?: array<string, mixed>}
     */
    abstract protected function calcular(RelatorioFiltros $filtros): array;

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

    /** @return array<string, mixed> */
    private function conteudo(RelatorioFiltros $filtros): array
    {
        $calculado = $this->calcular($filtros);

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
            'filtros_aplicados' => $calculado['filtros_aplicados'] ?? [],
            'kpis' => $calculado['kpis'],
            'series' => $calculado['series'],
            'tabelas' => $calculado['tabelas'],
            'gerado_em' => now()->toIso8601String(),
        ];
    }
}
