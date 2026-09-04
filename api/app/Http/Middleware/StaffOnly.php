<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Support\DefaultRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffOnly
{
    /**
     * A Family Member login only ever exists to reach the narrow
     * /family-portal endpoints — every other internal endpoint (which checks
     * tenant membership only, not role) would otherwise hand them every
     * client's full record, not just their own relative's.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->hasRole(DefaultRoles::FAMILY_MEMBER), 403);

        return $next($request);
    }
}
