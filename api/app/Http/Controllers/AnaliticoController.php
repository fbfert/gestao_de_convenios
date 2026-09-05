<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnaliticoUnimedLoteDetalheResource;
use App\Http\Resources\AnaliticoUnimedLoteResource;
use App\Models\AnaliticoUnimedLote;
use Illuminate\Http\Request;
use App\Support\OrdenaListagem;
use App\Support\PaginaListagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnaliticoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $lotes = AnaliticoUnimedLote::query()
            ->when($request->string('busca')->trim()->toString() !== '', function ($query) use ($request) {
                $busca = $request->string('busca')->trim()->toString();

                $query->where('arquivo_nome_original', 'like', "%{$busca}%");
            })
            ->when($request->string('status')->trim()->toString() !== '', function ($query) use ($request) {
                $query->where('status', $request->string('status')->trim()->toString());
            })
            ->when($request->string('importado_de')->trim()->toString() !== '', function ($query) use ($request) {
                $query->whereDate('importado_em', '>=', $request->string('importado_de')->trim()->toString());
            })
            ->when($request->string('importado_ate')->trim()->toString() !== '', function ($query) use ($request) {
                $query->whereDate('importado_em', '<=', $request->string('importado_ate')->trim()->toString());
            })
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query,
                $request->only(['ordenar_por', 'direcao']),
                [
                    'arquivo' => 'arquivo_nome_original',
                    'importado_em' => 'importado_em',
                    'linhas' => 'total_linhas_analitico',
                    'status' => 'status',
                ],
                padrao: 'importado_em',
                direcaoPadrao: 'desc',
                desempate: 'id',
            ));

        // Mesmo idioma de MedicoController@index/PacienteController@index:
        // sem `page` na query string devolve tudo; com `page`, pagina de
        // verdade (meta/links no formato padrão do Laravel).
        return AnaliticoUnimedLoteResource::collection(PaginaListagem::aplicar($lotes, $request));
    }

    public function show(AnaliticoUnimedLote $analiticoLote): JsonResponse
    {
        $analiticoLote->load('linhas');

        return response()->json([
            'data' => (new AnaliticoUnimedLoteDetalheResource($analiticoLote))->resolve(),
        ]);
    }
}
