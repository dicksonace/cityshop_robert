<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\ProductDiscoveryService;
use App\Services\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    public function __construct(
        private ProductDiscoveryService $discovery,
        private ProductSearchService $search,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) ($request->get('q') ?: $request->get('search', '')));
        $mode = (string) $request->get('mode', 'all');
        if (! in_array($mode, ['all', 'products', 'stores', 'ghana'], true)) {
            $mode = 'all';
        }

        if ($q === '') {
            return response()->json([
                'query' => '',
                'products' => [],
                'stores' => [],
            ]);
        }

        $products = $mode === 'stores'
            ? collect()
            : $this->matchingProducts($request, $q, $mode === 'ghana');

        $stores = in_array($mode, ['all', 'stores'], true)
            ? $this->matchingStores($q, 12)
            : collect();

        return response()->json([
            'query' => $q,
            'products' => ProductResource::collection($products)->resolve(),
            'stores' => $stores,
        ]);
    }

    /**
     * @return Collection<int, Product>
     */
    private function matchingProducts(Request $request, string $q, bool $inGhana): Collection
    {
        $query = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        $this->discovery->applySearch($query, $q);

        if ($inGhana) {
            $query->where('in_ghana', true);
        }

        $rankingSeed = $this->discovery->resolveRandomSeed($request);
        $this->discovery->applySort($query, 'relevance', $rankingSeed, $request->user());

        return $query->limit(40)->get();
    }

    /**
     * @return Collection<int, array{id: int, name: string, slug: string, shop_photo: string|null, products_count: int}>
     */
    private function matchingStores(string $q, int $limit): Collection
    {
        $parsed = $this->search->parseQuery($q);

        if ($parsed['raw'] === '' || $parsed['tokens'] === []) {
            return collect();
        }

        $profiles = SellerProfile::query()
            ->select(['id', 'user_id', 'store_name', 'business_name', 'slug', 'shop_photo'])
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->tap(fn ($query) => $this->search->constrainStoreName($query, $parsed))
            ->limit($limit * 3)
            ->get();

        $counts = $profiles->isEmpty()
            ? collect()
            : Product::query()
                ->visibleInShop()
                ->whereIn('seller_id', $profiles->pluck('user_id'))
                ->selectRaw('seller_id, COUNT(*) as products_count')
                ->groupBy('seller_id')
                ->pluck('products_count', 'seller_id');

        return $profiles
            ->map(function (SellerProfile $profile) use ($counts) {
                $photo = $profile->shop_photo;
                $photoUrl = null;
                if ($photo) {
                    $photoUrl = str_starts_with((string) $photo, 'http')
                        ? $photo
                        : Storage::disk('public')->url($photo);
                }

                return [
                    'id' => $profile->id,
                    'name' => $profile->displayName(),
                    'slug' => $profile->slug,
                    'shop_photo' => $photoUrl,
                    'products_count' => (int) ($counts[$profile->user_id] ?? 0),
                ];
            })
            ->sortByDesc('products_count')
            ->take($limit)
            ->values();
    }
}
