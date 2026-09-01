<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportGuiasRequest;
use App\Http\Requests\ImportGuiasRequest;
use App\Models\GuiaImportLote;
use App\Services\GuiaImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuiaImportController extends Controller
{
    public function __construct(
        private readonly GuiaImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportGuiasRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportGuiasRequest $request, GuiaImportLote $guia_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $guia_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
