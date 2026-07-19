<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSellerShopping
{
    public const MESSAGE = 'Seller accounts cannot buy products. Sign in with a buyer account to shop, or switch to your Seller Centre to manage your store.';

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSeller()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE], 403);
            }

            return redirect()
                ->to($user->defaultRedirectRoute())
                ->with('error', self::MESSAGE);
        }

        return $next($request);
    }
}
