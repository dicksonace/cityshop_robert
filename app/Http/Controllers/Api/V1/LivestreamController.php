<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LivestreamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => LiveStreamService::liveNow(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $store = SellerProfile::query()
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $live = LiveStreamService::currentForStore($store);

        return response()->json([
            'data' => $live ? LiveStreamService::card($live, withRoom: true, requireHostJoined: true) : null,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:80'],
        ]);

        $live = LiveStreamService::start($request->user(), $validated['title'] ?? null);

        return response()->json([
            'livestream' => LiveStreamService::card($live, withRoom: true),
        ], 201);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $live = LiveStreamService::heartbeat($request->user());

        return response()->json([
            'ok' => $live !== null,
            'livestream' => $live ? LiveStreamService::card($live, withRoom: true) : null,
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $live = LiveStreamService::end($request->user());

        return response()->json([
            'ok' => true,
            'livestream' => $live ? LiveStreamService::card($live, withRoom: false) : null,
        ]);
    }
}
