<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelatorioFiltrosRequest;
use App\Services\Relatorios\RelatorioAba;
use App\Services\Relatorios\RelatorioFiltros;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * As quatro abas de relatório, num controller só.
 *
 * A aba não vem da URL como texto livre: cada rota fixa a sua em `defaults()`
 * (ver routes/api.php), junto com o `permission:` correspondente. Uma rota por
 * aba é o que permite ao middleware exigir a permissão certa — com
 * `/relatorios/{aba}` genérico, o `permission:` teria que ser resolvido dentro
 * do controller, depois de a requisição já ter passado.
 */
class RelatorioController extends Controller
{
    public function show(RelatorioFiltrosRequest $request, string $aba): JsonResponse
    {
        $relatorio = RelatorioAba::servico($aba)->montar(RelatorioFiltros::doRequest($request));

        return response()->json(['data' => $relatorio]);
    }

    /**
     * Exporta uma das tabelas da aba, com os mesmos filtros da consulta.
     *
     * O arquivo em si é o bloco 3 do tasks.md. O que já vale aqui é a checagem
     * que não pode nascer depois: a tabela pedida precisa ser uma das que a aba
     * devolve. Sem ela, `?tabela=` viraria um nome livre entrando no serviço de
     * exportação.
     */
    public function export(RelatorioFiltrosRequest $request, string $aba): JsonResponse
    {
        $request->validate([
            'tabela' => ['required', 'string'],
            'formato' => ['required', 'in:csv,xlsx'],
        ]);

        $relatorio = RelatorioAba::servico($aba)->montar(RelatorioFiltros::doRequest($request));

        if (! collect($relatorio['tabelas'])->firstWhere('key', $request->string('tabela')->toString())) {
            throw new NotFoundHttpException('Tabela de relatório desconhecida.');
        }

        // 501, e não um arquivo vazio: um CSV com cabeçalho e nenhuma linha é
        // indistinguível de "não houve nada no período", e é assim que uma
        // exportação incompleta vira relatório impresso. O
        // RelatorioExportService entra aqui no bloco 3, em streaming.
        abort(SymfonyResponse::HTTP_NOT_IMPLEMENTED, 'A exportação de relatórios ainda não está disponível.');
    }
}
