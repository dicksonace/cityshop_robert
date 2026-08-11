<?php

namespace App\Http\Middleware;

use App\Services\ChatService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Run after route auth (Sanctum/session) so $request->user() is set on API.
        $user = $request->user() ?? $request->user('sanctum');
        if ($user) {
            $path = $request->path();
            $isCallSignal = str_ends_with($path, '/signal');
            // ICE/offer signalling is extremely hot — skip. Chat poll still
            // touches presence (throttled) so people in a thread stay Online.
            if (! $isCallSignal) {
                ChatService::touchPresence($user);
            }
        }

        return $response;
    }
}
