<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportPacientesRequest;
use App\Http\Requests\ImportPacientesRequest;
use App\Models\PacienteImportLote;
use App\Services\PacienteImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PacienteImportController extends Controller
{
    public function __construct(
        private readonly PacienteImportService $service
    ) {
    }

    public function template(): StreamedResponse
    {
        return $this->service->gerarTemplate();
    }

    public function previsualizar(ImportPacientesRequest $request): JsonResponse
    {
        $resultado = $this->service->previsualizar(
            $request->file('arquivo'),
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }

    public function confirmar(ConfirmarImportPacientesRequest $request, PacienteImportLote $paciente_import_lote): JsonResponse
    {
        $resultado = $this->service->confirmar(
            $paciente_import_lote,
            $request->validated('linha_ids'),
            $request->validated('edicoes') ?? [],
            (int) $request->user()->tenant_id,
        );

        return response()->json(['data' => $resultado]);
    }
}
