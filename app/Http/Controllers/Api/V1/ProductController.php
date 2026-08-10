<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\ProductAnalyticsService;
use App\Services\ProductDiscoveryService;
use App\Services\ReviewService;
use App\Services\SellerFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(
        private ProductDiscoveryService $discovery,
        private ProductAnalyticsService $analytics,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        $search = trim((string) ($request->get('search') ?: $request->get('q', '')));
        if ($search !== '') {
            $this->discovery->applySearch($query, $search);
        }

        if ($category = $request->get('category')) {
            $query->where('category_id', $category);
        }

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->filled('price_min')) {
            $query->whereRaw('COALESCE(discount_price, price) >= ?', [(float) $request->price_min]);
        }

        if ($request->filled('price_max')) {
            $query->whereRaw('COALESCE(discount_price, price) <= ?', [(float) $request->price_max]);
        }

        if ($rating = $request->get('rating')) {
            $query->where('rating', '>=', (float) $rating);
        }

        if ($request->boolean('in_ghana')) {
            $query->where('in_ghana', true);
        }

        if ($request->boolean('free_ship')) {
            $query->where('free_shipping', true);
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', (int) $request->get('seller_id'));
        }

        if ($sellerSlug = trim((string) $request->get('seller_slug', ''))) {
            $query->whereHas('seller.sellerProfile', fn ($q) => $q->where('slug', $sellerSlug));
        }

        // Legacy quick-filter support (same as web home)
        match ($request->get('filter')) {
            'in_ghana' => $query->where('in_ghana', true),
            'free_ship' => $query->where('free_shipping', true),
            default => null,
        };

        $sort = $request->get('sort', $search !== '' ? 'relevance' : 'recommended');
        $rankingSeed = $this->discovery->resolveRandomSeed($request);
        $this->discovery->applySort($query, $sort, $rankingSeed, $request->user());

        $paginator = $query
            ->paginate(min(50, max(1, (int) $request->get('per_page', 20))))
            ->withQueryString();

        return ProductResource::collection($paginator)->additional([
            'meta_extra' => [
                'seed' => $rankingSeed,
                'sort' => $sort,
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->where(ctype_digit($slug) ? 'id' : 'slug', $slug)
            ->firstOrFail();

        try {
            $this->analytics->recordView($product);
        } catch (\Throwable $e) {
            report($e);
        }

        $reviews = Review::with('user:id,name,avatar')
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(10, ['*'], 'reviews_page')
            ->withQueryString();

        $reviewable = null;
        if ($request->user()) {
            try {
                $item = ReviewService::findReviewableItem($request->user(), $product);
                if ($item) {
                    $reviewable = [
                        'order_id' => $item->order_id,
                        'order_item_id' => $item->id,
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $relatedQuery = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->where('id', '!=', $product->id);

        if ($product->category_id) {
            $relatedQuery->where('category_id', $product->category_id);
        }

        $this->discovery->applySort(
            $relatedQuery,
            'recommended',
            $this->discovery->explorationSeed($request),
            $request->user(),
        );

        $related = $relatedQuery->limit(12)->get();

        $isFollowingSeller = false;
        $followerCount = 0;
        if ($product->seller_id) {
            $followerCount = SellerFollowService::followerCount((int) $product->seller_id);
            if ($request->user()) {
                $isFollowingSeller = SellerFollowService::isFollowing(
                    $request->user(),
                    (int) $product->seller_id,
                );
            }
        }

        return response()->json([
            'data' => new ProductResource($product),
            'related' => ProductResource::collection($related),
            'is_following_seller' => $isFollowingSeller,
            'seller_follower_count' => $followerCount,
            'reviews' => [
                'data' => $reviews->getCollection()->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => (float) $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->toIso8601String(),
                    'user' => [
                        'id' => $review->user?->id,
                        'name' => $review->user?->name,
                    ],
                ])->values(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
            'reviewable' => $reviewable,
        ]);
    }

    public function matchesForRecentViews(Request $request): JsonResponse
    {
        $rawIds = $request->query('ids', []);

        if (is_string($rawIds)) {
            $rawIds = array_filter(explode(',', $rawIds));
        } elseif (! is_array($rawIds)) {
            $rawIds = [];
        }

        $ids = array_slice(array_values(array_unique(array_map('intval', $rawIds))), 0, 20);

        $products = $this->discovery->matchesForRecentViews($ids, $request->user(), 12);

        $categoryIds = $products
            ->pluck('category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sellerCounts = $this->discovery->sellerCountsByCategory($categoryIds);

        $payload = $products->map(function (Product $product) use ($sellerCounts) {
            $categoryId = $product->category_id ? (int) $product->category_id : null;
            $imagePath = ($product->images->firstWhere('is_primary', true) ?? $product->images->first())?->path;
            $imageUrl = null;
            if (is_string($imagePath) && $imagePath !== '') {
                $imageUrl = str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')
                    ? $imagePath
                    : Storage::disk('public')->url($imagePath);
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'discount_price' => $product->discount_price !== null ? (float) $product->discount_price : null,
                'effective_price' => (float) $product->effectivePrice(),
                'image_url' => $imageUrl,
                'category_id' => $categoryId,
                'sellers_in_category' => $categoryId ? ($sellerCounts[$categoryId] ?? 1) : 1,
            ];
        })->values();

        return response()->json(['products' => $payload]);
    }
}
