<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant-configurable idle logout (System Settings > General > Session
 * timeout). Laravel's session.lifetime config only governs cookie expiry,
 * not an enforced idle cutoff, so this tracks the session's own
 * last-activity timestamp and invalidates it once the tenant's configured
 * window has passed. No timeout configured (the default) means no
 * enforcement — existing behavior is unchanged until an admin opts in.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only cookie/session-based (stateful) requests carry a session at
        // all — Sanctum only attaches the session middleware for requests
        // from a configured stateful domain. A personal-access-token
        // request has no session to time out, so there's nothing to enforce.
        if (! $request->hasSession()) {
            return $next($request);
        }

        $user = $request->user();
        $timeoutMinutes = (int) ($user?->tenant?->setting('session_timeout_minutes') ?? 0);
        $lastActivity = $request->session()->get('last_activity_at');

        if ($timeoutMinutes > 0 && $lastActivity && (time() - $lastActivity) > $timeoutMinutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Your session has expired due to inactivity.'], 401);
        }

        $request->session()->put('last_activity_at', time());

        return $next($request);
    }
}
