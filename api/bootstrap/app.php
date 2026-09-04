<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\StaffOnly;
use Illuminate\Auth\AuthenticationException;
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
        $middleware->statefulApi();

        // Laravel's default guest redirect points at a "login" route, which this
        // API doesn't have. Authenticate::unauthenticated() only skips it when the
        // request expectsJson(), so a client that omits "Accept: application/json"
        // would blow up with a RouteNotFoundException (500) instead of a 401.
        // Returning null keeps every unauthenticated request on the JSON 401 path.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'staff-only' => StaffOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is a JSON-only API with no server-rendered login page, so an
        // unauthenticated request should never attempt Laravel's default
        // redirect-to-"login"-route fallback (which doesn't exist here and
        // would otherwise throw a RouteNotFoundException).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
