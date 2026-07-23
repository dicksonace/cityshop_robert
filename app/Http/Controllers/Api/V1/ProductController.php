<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\ProductDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private ProductDiscoveryService $discovery) {}

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

        if ($request->boolean('in_ghana')) {
            $query->where('in_ghana', true);
        }

        if ($request->boolean('free_ship')) {
            $query->where('free_shipping', true);
        }

        $sort = $request->get('sort', $search !== '' ? 'relevance' : 'recommended');
        $this->discovery->applySort($query, $sort, $request->get('seed'), $request->user());

        return ProductResource::collection(
            $query->paginate(min(50, max(1, (int) $request->get('per_page', 20))))->withQueryString()
        );
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->where('slug', $slug)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
