<?php

namespace App\Http\Controllers;

use App\Models\ClinicaConexaoConfig;
use App\Models\ClinicaSyncExecucao;
use App\Services\ClinicaSync\ClinicaSyncService;
use Illuminate\Http\JsonResponse;

class ClinicaSyncController extends Controller
{
    public function show(): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;

        $config = ClinicaConexaoConfig::where('tenant_id', $tenantId)->first();
        $ultima = ClinicaSyncExecucao::where('tenant_id', $tenantId)->latest('iniciado_em')->first();

        return response()->json([
            'configurado' => $config !== null && $config->ativo,
            'base_url' => $config?->base_url,
            'ultima_execucao' => $ultima === null ? null : $this->serializar($ultima),
        ]);
    }

    /**
     * Roda SÍNCRONO (não enfileira): quem clicou "Sincronizar Agora" quer o resultado
     * na hora, não um status pra ficar checando. O agendamento de 5 em 5 minutos
     * (routes/console.php) é quem usa a fila.
     */
    public function sincronizar(ClinicaSyncService $service): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;
        $execucao = $service->executar($tenantId, 'manual');

        return response()->json($this->serializar($execucao), $execucao->status === 'ok' ? 200 : 502);
    }

    private function serializar(ClinicaSyncExecucao $execucao): array
    {
        return [
            'origem' => $execucao->origem,
            'status' => $execucao->status,
            'iniciado_em' => $execucao->iniciado_em,
            'finalizado_em' => $execucao->finalizado_em,
            'resumo' => $execucao->resumo,
            'erro_mensagem' => $execucao->erro_mensagem,
        ];
    }
}
