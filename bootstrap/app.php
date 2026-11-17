<?php

use App\Http\Middleware\EnsureRegistrationApproved;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            EnsureRegistrationApproved::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Applicants are turned away before route model binding, so they
        // cannot probe which records exist (404 versus redirect).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRegistrationApproved::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A denied Inertia form submit goes back with the policy's reason as a
        // toast instead of replacing the page with an error screen.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 403 || ! $request->header('X-Inertia') || $request->isMethod('GET')) {
                return $response;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage() ?: __('This action is unauthorized.')]);

            return back();
        });
    })->create();
