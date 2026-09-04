<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Support\DefaultRoles;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;

        app(TenantContext::class)->set($tenantId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId ?? DefaultRoles::PLATFORM_TEAM_ID);

        return $next($request);
    }
}
