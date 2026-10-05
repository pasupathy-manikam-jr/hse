<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded error page with a way back. 403/404 always; server errors only outside
        // local development, where the debug page is more useful. An unmatched URL skips the
        // web middleware, so shared props are missing: the page gets what it needs directly.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (in_array($status, [403, 404], true) || ($status >= 500 && ! app()->isLocal())) {
                return Inertia::render('error', ['status' => $status, 'signedIn' => $request->user() !== null])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
