<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Services\ImageSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImageSearchController extends Controller
{
    public function __construct(private ImageSearchService $imageSearch) {}

    public function create(Request $request): Response
    {
        return Inertia::render('shop/image-search', [
            'results' => [],
            'preview' => null,
            'keywords' => [],
            'method' => null,
            'visionEnabled' => (bool) config('services.openai.key'),
            ...$this->storeScope($request),
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
            'store' => ['nullable', 'string', 'max:120'],
        ]);

        $sellerId = isset($validated['seller_id']) ? (int) $validated['seller_id'] : ($request->integer('seller_id') ?: null);
        $result = $this->imageSearch->search($validated['image'], $sellerId);

        $products = $result['products']->map(fn ($row) => [
            'product' => $row['product'],
            'match_percent' => $row['match_percent'],
        ])->values();

        return Inertia::render('shop/image-search', [
            'results' => $products,
            'preview' => $result['preview'],
            'keywords' => $result['keywords'],
            'method' => $result['method'],
            'visionEnabled' => (bool) config('services.openai.key'),
            ...$this->storeScope($request, $sellerId),
        ]);
    }

    /**
     * @return array{seller_id: int|null, store_slug: string|null, store_name: string|null}
     */
    private function storeScope(Request $request, ?int $sellerId = null): array
    {
        $sellerId = $sellerId ?: ($request->integer('seller_id') ?: null);
        $storeSlug = $request->string('store')->toString() ?: null;
        $storeName = null;

        if ($sellerId) {
            $profile = SellerProfile::query()
                ->where('user_id', $sellerId)
                ->where('status', SellerStatus::Approved)
                ->first();

            if ($profile) {
                $storeSlug = $storeSlug ?: $profile->slug;
                $storeName = $profile->business_name ?? $profile->store_name;
            }
        } elseif ($storeSlug) {
            $profile = SellerProfile::query()
                ->where('slug', $storeSlug)
                ->where('status', SellerStatus::Approved)
                ->first();

            if ($profile) {
                $sellerId = $profile->user_id;
                $storeName = $profile->business_name ?? $profile->store_name;
            }
        }

        return [
            'seller_id' => $sellerId,
            'store_slug' => $storeSlug,
            'store_name' => $storeName,
        ];
    }
}
