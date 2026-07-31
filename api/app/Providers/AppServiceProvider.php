<?php

namespace App\Providers;

use App\Models\Guia;
use App\Models\AutomacaoExecucao;
use App\Models\Antecipacao;
use App\Models\ConciliacaoFinanceira;
use App\Models\Convenio;
use App\Models\ConvenioRegra;
use App\Models\Especialidade;
use App\Models\TabelaValor;
use App\Models\Medico;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\User;
use App\Services\Automation\HttpUnimedWorkerClient;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UnimedWorkerClient::class, HttpUnimedWorkerClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('convenio', function ($value) {
            return Convenio::query()
                ->where('tenant_id', request()->user()?->tenant_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('regra', function ($value) {
            return ConvenioRegra::query()->where('tenant_id', request()->user()?->tenant_id)->whereKey($value)->firstOrFail();
        });
        Route::bind('valor', function ($value) {
            return TabelaValor::query()->where('tenant_id', request()->user()?->tenant_id)->whereKey($value)->firstOrFail();
        });

        Route::bind('solicitacao', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Solicitacao::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('solicitacaoItem', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return SolicitacaoItem::query()
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

        Route::bind('automacaoExecucao', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return AutomacaoExecucao::query()
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

        Route::bind('lancamento', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Lancamento::query()
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

        Route::bind('especialidade', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Especialidade::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('paciente', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Paciente::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('usuario', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('role', function ($value) {
            $tenantId = request()->user()?->tenant_id;

            return Role::query()
                ->where('tenant_id', $tenantId)
                ->where('guard_name', 'web')
                ->where('name', $value)
                ->firstOrFail();
        });
    }
}
