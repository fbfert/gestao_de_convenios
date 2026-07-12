<?php

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Exceptions\ConciliacaoStatusInvalidoException;
use App\Exceptions\ConvenioRegraNaoEncontradaException;
use App\Exceptions\GuiaStatusInvalidoException;
use App\Exceptions\SolicitacaoStatusInvalidoException;
use App\Exceptions\TabelaValorNaoEncontradaException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TabelaValorNaoEncontradaException|ConvenioRegraNaoEncontradaException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (GuiaStatusInvalidoException|AntecipacaoCotaEsgotadaException|SolicitacaoStatusInvalidoException|ConciliacaoStatusInvalidoException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();
