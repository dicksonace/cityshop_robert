<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\SellerFollow;
use App\Models\User;
use App\Services\SellerFollowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowingController extends Controller
{
    public function index(Request $request): Response
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

        return Inertia::render('shop/following', [
            'following' => $rows,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);

        try {
            $result = SellerFollowService::toggle($request->user(), $seller);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            $result['following']
                ? 'You are now following this seller.'
                : 'Unfollowed this seller.',
        );
    }
}
