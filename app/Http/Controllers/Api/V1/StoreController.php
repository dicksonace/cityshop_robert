<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Services\ProductDiscoveryService;
use App\Services\SellerFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __construct(private ProductDiscoveryService $discovery) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $store = SellerProfile::with(['user'])
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $search = trim((string) ($request->get('search') ?: $request->get('q', '')));
        $categoryId = $request->integer('category') ?: null;

        $baseQuery = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->where('seller_id', $store->user_id);

        $productQuery = clone $baseQuery;

        if ($search !== '') {
            $this->discovery->applySearch($productQuery, $search);
        }

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $this->discovery->applySort(
                $productQuery,
                'relevance',
                $this->discovery->resolveRandomSeed($request),
                $request->user(),
            );
        } else {
            $productQuery->latest();
        }

        $products = $productQuery
            ->paginate(min(50, max(1, (int) $request->get('per_page', 24))))
            ->withQueryString();

        $productCount = (clone $baseQuery)->count();
        $reviewCount = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $store->user_id))
            ->count();

        $shopPhoto = $store->shop_photo;
        $shopPhotoUrl = null;
        if ($shopPhoto) {
            $shopPhotoUrl = str_starts_with((string) $shopPhoto, 'http')
                ? $shopPhoto
                : Storage::disk('public')->url($shopPhoto);
        }

        $user = $store->user;
        $viewer = $request->user();
        $followerCount = SellerFollowService::followerCount((int) $store->user_id);
        $isFollowing = $viewer
            ? SellerFollowService::isFollowing($viewer, (int) $store->user_id)
            : false;

        return response()->json([
            'data' => [
                'id' => $store->id,
                'seller_id' => $store->user_id,
                'store_name' => $store->displayName(),
                'seller_name' => $user?->name,
                'slug' => $store->slug,
                'shop_photo' => $shopPhotoUrl,
                'store_description' => $store->store_description,
                'business_address' => $store->business_address,
                'is_business_registered' => (bool) $store->is_business_registered,
                'approved_at' => $store->approved_at?->toIso8601String(),
                'rating' => $store->rating !== null ? (float) $store->rating : null,
                'total_sales' => $store->total_sales !== null ? (int) $store->total_sales : null,
                'product_count' => $productCount,
                'review_count' => $reviewCount,
                'follower_count' => $followerCount,
                'is_following' => $isFollowing,
                'city' => $user?->city,
                'region' => $user?->region,
                'email' => $user?->email,
                'mobile' => $user?->mobile,
                'whatsapp' => $user?->whatsapp,
                'digital_address' => $user?->digital_address,
                'residential_address' => $user?->residential_address,
            ],
            'products' => [
                'data' => ProductResource::collection($products->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ],
            'search' => $search !== '' ? $search : null,
        ]);
    }
}
