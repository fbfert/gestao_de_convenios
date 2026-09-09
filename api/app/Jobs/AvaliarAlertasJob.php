<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Alertas\AvaliadorDeAlertas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Roda o avaliador de alertas para todos os tenants.
 *
 * Um tenant que exploda nao pode derrubar os outros: a excecao e registrada e a
 * varredura segue. Alerta e vigilancia — falhar em silencio para todo mundo por
 * causa de um seria o pior resultado possivel.
 */
class AvaliarAlertasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AvaliadorDeAlertas $avaliador): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($avaliador) {
            try {
                $avaliador->avaliarTenant((int) $tenant->id);
            } catch (Throwable $erro) {
                Log::error('Falha ao avaliar alertas do tenant', [
                    'tenant_id' => $tenant->id,
                    'erro' => $erro->getMessage(),
                ]);
            }
        });
    }
}
