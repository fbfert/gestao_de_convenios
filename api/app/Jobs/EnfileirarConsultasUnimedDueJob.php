<?php

namespace App\Jobs;

use App\Models\Guia;
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

    public function handle(ConsultarStatusUnimedService $consultarStatus): void
    {
        Guia::query()
            ->with('convenio')
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->where(function ($query) {
                $query->whereNull('unimed_next_check_at')
                    ->orWhere('unimed_next_check_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('senha')
                    ->orWhereNull('validade_senha');
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (Guia $guia) use ($consultarStatus) {
                $lock = Cache::lock("automacao:unimed:due:tenant:{$guia->tenant_id}", 60);

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
    }
}
