<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('throttle:webhooks')
                ->prefix('webhooks')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ngrok / reverse proxies terminate TLS; trust X-Forwarded-* so asset() and
        // route() emit https:// (avoids mixed-content blocks on the tunnel URL).
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Webhooks only - authenticated canvas /api/* routes use session cookies and must stay CSRF-protected
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'webhooks/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // API rate limiting (60 requests per minute per user)
        $middleware->alias([
            'cache.response' => \App\Http\Middleware\CacheResponse::class,
            'throttle.user' => \App\Http\Middleware\ThrottlePerUser::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'has.credits' => \App\Http\Middleware\EnsureUserHasCredits::class,
            'can.view.projects' => \App\Http\Middleware\EnsureCanViewProjects::class,
            'not.suspended' => \App\Http\Middleware\EnsureUserNotSuspended::class,
            'check.project.limit' => \App\Http\Middleware\CheckProjectLimit::class,
            'check.csv.limit' => \App\Http\Middleware\CheckCsvRowLimit::class,
        ]);

        // Throttle API endpoints
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (!in_array($status, [301, 302, 303, 307, 308]) && !$request->expectsJson() && $request->header('X-Inertia')) {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            if (!in_array($status, [301, 302, 303, 307, 308]) && !$request->expectsJson() && !$request->isXmlHttpRequest()) {
                if (in_array($status, [400, 403, 404, 419, 429, 500, 503])) {
                    return Inertia::render('error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                }
            }

            return $response;
        });
    })->create();
