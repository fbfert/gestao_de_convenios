<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportLancamentosRequest;
use App\Http\Requests\ImportLancamentosRequest;
use App\Models\LancamentoImportLote;
use App\Services\LancamentoImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LancamentoImportController extends Controller
{
    public function __construct(
        private readonly LancamentoImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportLancamentosRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportLancamentosRequest $request, LancamentoImportLote $lancamento_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $lancamento_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
