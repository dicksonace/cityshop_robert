<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreSetupComplete
{
    /** @var list<string> */
    protected array $except = [
        'seller.store-setup',
        'seller.store-appearance.index',
        'seller.store-appearance.draft',
        'seller.store-appearance.publish',
        'seller.store-appearance.reset',
        'seller.store-appearance.complete-setup',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->route()?->getName(), $this->except, true)) {
            return $next($request);
        }

        $profile = $request->user()?->sellerProfile;
        $customization = $profile?->storeCustomization;

        if (! $customization || ! $customization->isSetupComplete()) {
            return redirect()->route('seller.store-setup');
        }

        return $next($request);
    }
}
