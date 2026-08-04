<?php

namespace App\Jobs;

use App\Models\ConectorExecucao;
use App\Models\Guia;
use App\Services\Connectors\ConnectorResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerificarGuiasDiarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ConnectorResolver $resolver): void
    {
        Guia::query()
            ->where('status', 'under_review')
            ->whereHas('convenio', fn ($query) => $query->where(function ($query) {
                $query->whereNull('connector_driver')
                    ->orWhere('connector_driver', '!=', 'unimed_rda');
            }))
            ->with('convenio')
            ->orderBy('convenio_id')
            ->get()
            ->groupBy('convenio_id')
            ->each(function ($guias) use ($resolver) {
                $convenio = $guias->first()->convenio;
                $connector = $resolver->resolver($convenio);

                foreach ($guias as $guia) {
                    $resultado = $connector->checar($guia);

                    ConectorExecucao::query()->create([
                        'tenant_id' => $guia->tenant_id,
                        'convenio_id' => $guia->convenio_id,
                        'executado_em' => now(),
                        'status' => $resultado['status'],
                        'detalhes' => $resultado['detalhes'] ?? null,
                    ]);
                }
            });
    }
}
