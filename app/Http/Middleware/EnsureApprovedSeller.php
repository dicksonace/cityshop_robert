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

        if (! $profile) {
            return redirect()->route('seller.pending');
        }

        if ($profile->status === SellerStatus::Suspended) {
            return redirect()->route('seller.pending');
        }

        if ($profile->status !== SellerStatus::Approved) {
            return redirect()->route('seller.pending');
        }

        return $next($request);
    }
}
