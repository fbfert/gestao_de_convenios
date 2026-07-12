<?php

namespace App\Providers;

use App\Models\Guia;
use App\Models\Antecipacao;
use App\Models\ConciliacaoFinanceira;
use App\Models\Medico;
use App\Models\Solicitacao;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('solicitacao', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Solicitacao::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('guia', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Guia::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('antecipacao', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Antecipacao::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('conciliacao', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return ConciliacaoFinanceira::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('medico', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Medico::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
