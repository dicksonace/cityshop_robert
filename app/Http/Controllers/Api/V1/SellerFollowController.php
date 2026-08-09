<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SellerFollow;
use App\Models\User;
use App\Services\SellerFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerFollowController extends Controller
{
    /** Sellers the authenticated user follows. */
    public function following(Request $request): JsonResponse
    {
        $rows = SellerFollow::query()
            ->with(['seller.sellerProfile'])
            ->where('follower_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function (SellerFollow $follow) {
                return [
                    'id' => $follow->id,
                    'followed_at' => $follow->created_at?->toIso8601String(),
                    'seller' => SellerFollowService::sellerCard($follow->seller),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    /** People following the authenticated seller. */
    public function followers(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== UserRole::Seller) {
            return response()->json(['message' => 'Only sellers have followers.'], 403);
        }

        $rows = SellerFollow::query()
            ->with('follower')
            ->where('seller_id', $user->id)
            ->latest()
            ->get()
            ->map(function (SellerFollow $follow) {
                return [
                    'id' => $follow->id,
                    'followed_at' => $follow->created_at?->toIso8601String(),
                    'user' => SellerFollowService::publicUserCard($follow->follower),
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);

        try {
            $result = SellerFollowService::toggle($request->user(), $seller);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'following' => $result['following'],
            'follower_count' => $result['follower_count'],
            'message' => $result['following']
                ? 'You are now following this seller.'
                : 'Unfollowed this seller.',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
        ]);

        $sellerId = (int) $validated['seller_id'];

        return response()->json([
            'following' => SellerFollowService::isFollowing($request->user(), $sellerId),
            'follower_count' => SellerFollowService::followerCount($sellerId),
        ]);
    }
}
