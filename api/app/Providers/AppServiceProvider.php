<?php

namespace App\Providers;

use App\Models\Guia;
use App\Models\AutomacaoExecucao;
use App\Models\ConciliacaoFinanceira;
use App\Models\Alerta;
use App\Models\AlertaDestinatario;
use App\Models\AlertaRegra;
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
use App\Support\GuardaDeBancoDestrutivo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Primeira coisa do boot, de proposito: se um comando destrutivo apontar
        // para um banco que nao e descartavel, ele para antes de qualquer outra
        // coisa acontecer. Ver App\Support\GuardaDeBancoDestrutivo.
        GuardaDeBancoDestrutivo::aplicar();

        /*
         * Limite de tentativas de login: 5 por minuto, como sempre foi.
         *
         * O limite e DADO, e nao um `if` por ambiente. A primeira tentativa aqui
         * foi ramificar em `environment('testing')`, e estava errada: o phpunit e
         * a suite ponta a ponta rodam os DOIS com APP_ENV=testing, e o
         * AuthApiTest existe justamente para provar que a sexta tentativa e
         * barrada. Ramificar por ambiente afrouxava o limite no teste que o
         * verifica — ou seja, quebrava a garantia para acomodar a ferramenta.
         *
         * Com o limite em variavel de ambiente, o phpunit continua nos 5 padrao e
         * so o `.env.testing` da suite ponta a ponta sobe o teto, porque la sao
         * oito logins de navegador em menos de um minuto.
         */
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) env('LOGIN_TENTATIVAS_POR_MINUTO', 5))
                ->by($request->ip());
        });

        // ADR-13: o binding implícito resolve pelo id cru antes do ResolveTenant
        // existir, então todo model de rota precisa de bind explícito por tenant
        // — senão um usuário do tenant A alcança um registro do tenant B por id.
        Route::bind('alerta', function ($value) {
            return Alerta::query()
                ->where('tenant_id', request()->user()?->tenant_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('alertaRegra', function ($value) {
            return AlertaRegra::query()
                ->where('tenant_id', request()->user()?->tenant_id)
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('alertaDestinatario', function ($value) {
            return AlertaDestinatario::query()
                ->where('tenant_id', request()->user()?->tenant_id)
                ->whereKey($value)
                ->firstOrFail();
        });

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
