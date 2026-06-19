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

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map(fn ($r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
