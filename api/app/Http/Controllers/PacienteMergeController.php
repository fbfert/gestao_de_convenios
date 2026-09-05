<?php

namespace App\Http\Controllers;

use App\Http\Resources\PacienteResource;
use App\Services\PacienteDuplicadoService;
use App\Services\PacienteMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PacienteMergeController extends Controller
{
    public function duplicados(PacienteDuplicadoService $service): JsonResponse
    {
        return response()->json($service->buscar((int) request()->user()->tenant_id));
    }

    public function preview(Request $request, PacienteMergeService $service): JsonResponse
    {
        $dados = $request->validate([
            'vencedor_id' => ['required', 'integer'],
            'perdedor_id' => ['required', 'integer'],
        ]);

        try {
            $resumo = $service->preview((int) $request->user()->tenant_id, (int) $dados['vencedor_id'], (int) $dados['perdedor_id']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['perdedor_id' => [$e->getMessage()]]);
        }

        return response()->json($resumo);
    }

    /** PacienteResource devolvido direto (não via response()->json()) pra pegar o wrapper `{"data": ...}` automático, igual PacienteController::update. */
    public function mesclar(Request $request, PacienteMergeService $service): PacienteResource
    {
        $dados = $request->validate([
            'vencedor_id' => ['required', 'integer'],
            'perdedor_id' => ['required', 'integer'],
            'clinica_id_escolhido' => ['nullable', 'integer'],
        ]);

        try {
            $vencedor = $service->mesclar(
                (int) $request->user()->tenant_id,
                (int) $dados['vencedor_id'],
                (int) $dados['perdedor_id'],
                isset($dados['clinica_id_escolhido']) ? (int) $dados['clinica_id_escolhido'] : null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['perdedor_id' => [$e->getMessage()]]);
        }

        return new PacienteResource($vencedor);
    }
}
