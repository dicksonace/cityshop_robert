<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LivestreamController extends Controller
{
    public function show(Request $request): Response
    {
        $live = LiveStreamService::currentForSeller($request->user());

        return Inertia::render('seller/livestream', [
            'livestream' => $live ? LiveStreamService::card($live, withRoom: true) : null,
            'storeUrl' => $request->user()->sellerProfile
                ? route('store.show', $request->user()->sellerProfile->slug, absolute: true)
                : null,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:80'],
        ]);

        LiveStreamService::start($request->user(), $validated['title'] ?? null);

        return back()->with('success', 'You are live. Allow camera and microphone when the browser asks.');
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $live = LiveStreamService::heartbeat($request->user());

        return response()->json([
            'ok' => $live !== null,
            'livestream' => $live ? LiveStreamService::card($live, withRoom: true) : null,
        ]);
    }

    public function end(Request $request): RedirectResponse
    {
        LiveStreamService::end($request->user());

        return back()->with('success', 'Live ended.');
    }
}
