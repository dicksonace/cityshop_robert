<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Services\ImageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageSearchController extends Controller
{
    public function __construct(private ImageSearchService $imageSearch) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $sellerId = isset($validated['seller_id']) ? (int) $validated['seller_id'] : null;
        $result = $this->imageSearch->search($validated['image'], $sellerId);

        $products = $result['products']->map(function (array $row) {
            /** @var \App\Models\Product $product */
            $product = $row['product'];
            $product->loadMissing(['images', 'seller.sellerProfile', 'category']);

            return (new ProductResource($product))->resolve() + [
                'match_percent' => $row['match_percent'] ?? null,
            ];
        })->values();

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
                'method' => $result['method'],
                'keywords' => $result['keywords'],
                'preview' => $result['preview'],
            ],
        ]);
    }
}
