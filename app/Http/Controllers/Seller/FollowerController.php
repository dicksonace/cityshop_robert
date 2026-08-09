<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerFollow;
use App\Services\SellerFollowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowerController extends Controller
{
    public function index(Request $request): Response
    {
        $seller = $request->user();

        $followers = SellerFollow::query()
            ->with('follower')
            ->where('seller_id', $seller->id)
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(function (SellerFollow $follow) {
                return [
                    'id' => $follow->id,
                    'followed_at' => $follow->created_at?->toIso8601String(),
                    'user' => SellerFollowService::publicUserCard($follow->follower),
                ];
            });

        return Inertia::render('seller/followers/index', [
            'followers' => $followers,
            'total' => SellerFollowService::followerCount($seller->id),
        ]);
    }
}
