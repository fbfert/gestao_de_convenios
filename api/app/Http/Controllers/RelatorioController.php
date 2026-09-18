<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelatorioFiltrosRequest;
use App\Services\Relatorios\RelatorioAba;
use App\Services\Relatorios\RelatorioExportService;
use App\Services\Relatorios\RelatorioFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
     * A tabela sai do MESMO `montar()` que alimenta a tela — inclusive do mesmo
     * cache. Reconsultar aqui abriria espaço para o arquivo divergir do que a
     * pessoa viu antes de clicar em exportar.
     *
     * A tabela pedida precisa ser uma das que a aba devolve: sem essa checagem,
     * `?tabela=` seria um nome livre entrando no serviço de exportação.
     */
    public function export(RelatorioFiltrosRequest $request, string $aba): StreamedResponse
    {
        $request->validate([
            'tabela' => ['required', 'string'],
            'formato' => ['required', Rule::in(RelatorioExportService::FORMATOS)],
        ]);

        $filtros = RelatorioFiltros::doRequest($request);
        $relatorio = RelatorioAba::servico($aba)->montar($filtros);

        $tabela = collect($relatorio['tabelas'])->firstWhere('key', $request->string('tabela')->toString());

        if (! $tabela) {
            throw new NotFoundHttpException('Tabela de relatório desconhecida.');
        }

        return app(RelatorioExportService::class)->exportar(
            $aba,
            $request->string('formato')->toString(),
            $tabela,
            $filtros,
        );
    }
}
