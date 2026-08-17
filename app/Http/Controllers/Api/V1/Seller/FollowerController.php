<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerFollow;
use App\Services\SellerFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seller = $request->user();
        $followers = SellerFollow::query()
            ->with('follower')
            ->where('seller_id', $seller->id)
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 30), 1), 50));

        return response()->json([
            'data' => $followers->getCollection()->map(fn (SellerFollow $follow) => [
                'id' => $follow->id,
                'followed_at' => $follow->created_at?->toIso8601String(),
                'user' => SellerFollowService::publicUserCard($follow->follower),
            ])->values(),
            'meta' => [
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
                'total' => $followers->total(),
            ],
            'total' => SellerFollowService::followerCount($seller->id),
        ]);
    }
}
