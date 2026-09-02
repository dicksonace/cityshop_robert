<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            if ($wantsJson) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($request->is('admin24') || $request->is('admin24/*')) {
                return redirect()->route('admin.login');
            }

            if ($request->is('seller') || $request->is('seller/*')) {
                return redirect()->route('seller.login');
            }

            return redirect()->route('login');
        }

        $allowed = array_map(fn ($r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            if ($wantsJson) {
                abort(403, 'Unauthorized.');
            }

            if (($request->is('seller') || $request->is('seller/*')) && $user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if (($request->is('admin24') || $request->is('admin24/*')) && $user->isSeller()) {
                return redirect()->route('seller.dashboard');
            }

            // Buyers who tap a seller-only link (broken notification fallback) should not see a raw 403.
            if (($request->is('seller') || $request->is('seller/*')) && $user->isBuyer()) {
                return redirect()
                    ->route('home')
                    ->with('error', 'That page is only for sellers. Open My Orders to track your purchases.');
            }

            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
