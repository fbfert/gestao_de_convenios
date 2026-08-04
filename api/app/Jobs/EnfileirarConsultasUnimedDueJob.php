<?php

namespace App\Jobs;

use App\Models\Guia;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EnfileirarConsultasUnimedDueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('automacoes');
    }

    public function handle(
        ConsultarStatusUnimedService $consultarStatus,
        CapturarSenhaValidadeUnimedService $capturarSenhaValidade,
    ): void
    {
        Guia::query()
            ->with('convenio')
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->whereNotNull('numero_guia')
            ->whereNotIn('status', ['approved', 'denied', 'canceled', 'finalized', 'needs_verification'])
            ->where(function ($query) {
                $query->whereNull('unimed_next_check_at')
                    ->orWhere('unimed_next_check_at', '<=', now());
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (Guia $guia) use ($consultarStatus) {
                $lock = Cache::lock("automacao:unimed:due:tenant:{$guia->tenant_id}:consult_status_batch", 60);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $consultarStatus->enviar($guia);
                } catch (ValidationException) {
                    // Guia deixou de ser elegivel entre a busca e o enqueue.
                } finally {
                    $lock->release();
                }
            });

        Guia::query()
            ->with('convenio')
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->where('status', 'approved')
            ->whereNotNull('numero_guia')
            ->where(function ($query) {
                $query->whereNull('senha')
                    ->orWhereNull('validade_senha');
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (Guia $guia) use ($capturarSenhaValidade) {
                $lock = Cache::lock("automacao:unimed:due:tenant:{$guia->tenant_id}:capture_authorization_data_batch", 60);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $capturarSenhaValidade->enviar($guia);
                } catch (ValidationException) {
                    // Guia deixou de ser elegivel entre a busca e o enqueue.
                } finally {
                    $lock->release();
                }
            });
    }
}
