<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a gestão de tenants a quem tem `users.super_admin`.
 *
 * Não usa o middleware `permission:` do Spatie porque as permissões são por
 * tenant e editáveis pelo próprio administrador do tenant — ver a migration
 * 2026_08_12_180000.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->ehSuperAdmin()) {
            abort(403, 'Esta área é restrita à administração do sistema.');
        }

        return $next($request);
    }
}
