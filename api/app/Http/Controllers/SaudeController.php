<?php

namespace App\Http\Controllers;

use App\Services\SaudeService;
use Illuminate\Http\JsonResponse;

/**
 * Saude dos componentes do tenant, lida pelo card do dashboard.
 *
 * Diferente do HealthController: aquele e publico, responde a um monitor externo
 * sobre a infraestrutura e decide pelo status HTTP. Este e autenticado, mostra
 * as pecas daquela clinica e sempre responde 200 — quem esta fora aparece no
 * corpo, porque aqui quem le e uma tela, nao um alarme.
 */
class SaudeController extends Controller
{
    public function __invoke(SaudeService $saude): JsonResponse
    {
        return response()->json([
            'data' => $saude->componentesDoTenant(),
        ]);
    }
}
