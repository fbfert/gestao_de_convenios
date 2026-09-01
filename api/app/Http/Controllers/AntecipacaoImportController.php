<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportAntecipacoesRequest;
use App\Http\Requests\ImportAntecipacoesRequest;
use App\Models\AntecipacaoImportLote;
use App\Services\AntecipacaoImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AntecipacaoImportController extends Controller
{
    public function __construct(
        private readonly AntecipacaoImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportAntecipacoesRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportAntecipacoesRequest $request, AntecipacaoImportLote $antecipacao_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $antecipacao_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
