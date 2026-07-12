<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::clear();

        if ($request->user()) {
            $tenantId = $request->user()->tenant_id;
            TenantContext::set($tenantId);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        } else {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        return $next($request);
    }
}
