<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportSolicitacoesRequest;
use App\Http\Requests\ImportSolicitacoesRequest;
use App\Models\SolicitacaoImportLote;
use App\Services\SolicitacaoImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SolicitacaoImportController extends Controller
{
    public function __construct(
        private readonly SolicitacaoImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportSolicitacoesRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportSolicitacoesRequest $request, SolicitacaoImportLote $solicitacao_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $solicitacao_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
