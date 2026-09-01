<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportConciliacoesRequest;
use App\Http\Requests\ImportConciliacoesRequest;
use App\Models\ConciliacaoImportLote;
use App\Services\ConciliacaoImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConciliacaoImportController extends Controller
{
    public function __construct(
        private readonly ConciliacaoImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportConciliacoesRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportConciliacoesRequest $request, ConciliacaoImportLote $conciliacao_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $conciliacao_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
