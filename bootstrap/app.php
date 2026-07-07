<?php

use App\Http\Middleware\EnsureApprovedSeller;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreSetupComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->validateCsrfTokens(except: [
            'webhooks/paystack',
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'seller.approved' => EnsureApprovedSeller::class,
            'seller.store-setup' => EnsureStoreSetupComplete::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackUserPresence::class,
        ]);

        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => $request->user()?->defaultRedirectRoute() ?? route('home'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
