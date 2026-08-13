<?php

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Exceptions\ConciliacaoStatusInvalidoException;
use App\Exceptions\ConvenioRegraNaoEncontradaException;
use App\Exceptions\GuiaStatusInvalidoException;
use App\Exceptions\SolicitacaoStatusInvalidoException;
use App\Exceptions\TabelaValorNaoEncontradaException;
use App\Support\Auditoria;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Atrás do proxy Apache (X-Forwarded-Proto/For) — gera URLs https corretas
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->api(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
          Acesso negado entra na trilha. Devolver null faz o Laravel renderizar
          a resposta padrao: aqui o objetivo e so registrar, nunca mudar corpo
          nem status do que o cliente ja recebia. Cobre o middleware
          `permission:` do Spatie (UnauthorizedException estende HttpException)
          e o abort(403) do EnsureSuperAdmin.
        */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403 && $request->user()) {
                Auditoria::registrar(
                    acao: 'acesso.negado',
                    entidade: 'users',
                    entidadeId: (int) $request->user()->id,
                    payload: ['rota' => $request->method().' '.$request->path()],
                    tenantId: (int) $request->user()->tenant_id,
                    comOrigem: true,
                );
            }

            return null;
        });

        $exceptions->render(function (TabelaValorNaoEncontradaException|ConvenioRegraNaoEncontradaException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (GuiaStatusInvalidoException|AntecipacaoCotaEsgotadaException|SolicitacaoStatusInvalidoException|ConciliacaoStatusInvalidoException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();
