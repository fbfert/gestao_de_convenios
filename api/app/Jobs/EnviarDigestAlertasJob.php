<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Alertas\NotificadorDeAlertas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Digest diario, disparado DE HORA EM HORA.
 *
 * Um job por hora que olha quem tem `horario_digest` naquela hora, e nao 24
 * agendamentos fixos: sao 24 entradas no scheduler para fazer o que uma consulta
 * resolve.
 */
class EnviarDigestAlertasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificadorDeAlertas $notificador): void
    {
        $hora = (int) now()->format('G');

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($notificador, $hora) {
            try {
                $notificador->enviarDigestDoTenant((int) $tenant->id, $hora);
            } catch (Throwable $erro) {
                Log::error('Falha no digest de alertas do tenant', [
                    'tenant_id' => $tenant->id,
                    'erro' => $erro->getMessage(),
                ]);
            }
        });

        try {
            // Um e-mail so, com todos os tenants agrupados.
            $notificador->enviarDigestGlobal($hora);
        } catch (Throwable $erro) {
            Log::error('Falha no digest global de alertas', ['erro' => $erro->getMessage()]);
        }
    }
}
