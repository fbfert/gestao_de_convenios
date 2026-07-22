<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnaliticoUnimedLoteDetalheResource;
use App\Http\Resources\AnaliticoUnimedLoteResource;
use App\Models\AnaliticoUnimedLote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnaliticoController extends Controller
{
    public function index(Request $request): JsonResponse
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
            ->orderByDesc('importado_em')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => AnaliticoUnimedLoteResource::collection($lotes)->resolve(),
        ]);
    }

    public function show(AnaliticoUnimedLote $analiticoLote): JsonResponse
    {
        $analiticoLote->load('linhas');

        return response()->json([
            'data' => (new AnaliticoUnimedLoteDetalheResource($analiticoLote))->resolve(),
        ]);
    }
}
