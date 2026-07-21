<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnaliticoUnimedLoteResource;
use App\Models\AnaliticoUnimedLote;
use Illuminate\Http\JsonResponse;

class AnaliticoController extends Controller
{
    public function index(): JsonResponse
    {
        $lotes = AnaliticoUnimedLote::query()
            ->orderByDesc('importado_em')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => AnaliticoUnimedLoteResource::collection($lotes)->resolve(),
        ]);
    }
}
