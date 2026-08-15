<?php

namespace App\Http\Middleware;

use App\Enums\SellerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isSeller()) {
            abort(403);
        }

        $profile = $user->sellerProfile;
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $profile) {
            return $wantsJson
                ? response()->json(['message' => 'Seller profile not found.'], 403)
                : redirect()->route('seller.pending');
        }

        if ($profile->status === SellerStatus::Suspended) {
            return $wantsJson
                ? response()->json(['message' => 'Your seller account is suspended.'], 403)
                : redirect()->route('seller.pending');
        }

        if ($profile->status !== SellerStatus::Approved) {
            return $wantsJson
                ? response()->json(['message' => 'Your seller account is not active yet.'], 403)
                : redirect()->route('seller.pending');
        }

        return $next($request);
    }
}
