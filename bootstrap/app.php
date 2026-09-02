<?php

use App\Http\Middleware\EnsureApprovedSeller;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreSetupComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventSellerShopping;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
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
            'webhooks/formula-dc',
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'seller.approved' => EnsureApprovedSeller::class,
            'seller.store-setup' => EnsureStoreSetupComplete::class,
            'buyer.shop' => PreventSellerShopping::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackUserPresence::class,
        ]);

        $middleware->api(append: [
            TrackUserPresence::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin24') || $request->is('admin24/*')) {
                return route('admin.login');
            }

            if ($request->is('seller') || $request->is('seller/*')) {
                return route('seller.login');
            }

            return route('login');
        });

        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => $request->user()?->defaultRedirectRoute() ?? route('home'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // A page left open past the session lifetime posts with a dead CSRF token.
        // Without this the buyer just gets a raw error page (or nothing at all on
        // an Inertia visit), which reads as "the button does nothing".
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('error', 'Your session expired. Please try that again.');
            }

            return $response;
        });
    })->create();
