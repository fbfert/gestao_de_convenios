<?php

namespace App\Jobs;

use App\Models\ClinicaConexaoConfig;
use App\Services\ClinicaSync\ClinicaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SincronizarClinicaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $origem = 'agendado') {}

    public function handle(ClinicaSyncService $service): void
    {
        ClinicaConexaoConfig::where('ativo', true)->pluck('tenant_id')->each(
            fn (int $tenantId) => $service->executar($tenantId, $this->origem)
        );
    }
}
