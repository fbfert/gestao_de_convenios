<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Regra de existência que só vê a clínica de quem está pedindo.
 *
 * Existe porque `exists:tabela,id` roda no query builder, e não no Eloquent:
 * o `TenantScope` não se aplica, e a regra passa a enxergar todas as clínicas.
 *
 * O dado alheio não fica acessível — o código adiante refaz a consulta com o
 * escopo e devolve 404. O que vaza é a EXISTÊNCIA: id de outra clínica passa
 * na validação e morre depois (404), enquanto id inexistente é recusado na
 * validação (422). A diferença entre as duas respostas, repetida número a
 * número, enumera a base da clínica vizinha sem nunca mostrar um registro —
 * quantos pacientes ela tem, quantas guias, quantos convênios.
 *
 * Com o recorte, as duas respostas viram a mesma: id que não é seu é id que
 * não existe.
 *
 * O padrão nasceu em `RelatorioFiltrosRequest::existeNaClinica()`, que já
 * explicava o problema; isto é aquele mesmo raciocínio num lugar que todos os
 * FormRequests podem usar.
 */
trait ExisteNaClinica
{
    /**
     * Existe, e é da clínica do usuário autenticado.
     *
     * Sem usuário autenticado o recorte fica num tenant nulo, que não casa com
     * nada e recusa tudo: falha fechada, no mesmo espírito de
     * `BelongsToTenant::resolveRouteBinding()`.
     */
    protected function existeNaClinica(string $tabela, string $coluna = 'id'): Exists
    {
        $tenantId = $this->user()?->tenant_id;

        return Rule::exists($tabela, $coluna)
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));
    }
}
