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
        if ($user = $request->user()) {
            ChatService::touchPresence($user);
        }

        return $next($request);
    }
}
