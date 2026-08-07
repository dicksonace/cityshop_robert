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
        // Poll/signal fire many times per second during calls — skip the
        // users.last_seen_at write so presence updates don't amplify the load.
        if ($user = $request->user()) {
            $route = $request->route()?->getName() ?? '';
            $path = $request->path();
            $isChatHotPath = str_contains($route, 'poll')
                || str_contains($route, 'signal')
                || str_ends_with($path, '/poll')
                || str_ends_with($path, '/signal');

            if (! $isChatHotPath) {
                ChatService::touchPresence($user);
            }
        }

        return $next($request);
    }
}
