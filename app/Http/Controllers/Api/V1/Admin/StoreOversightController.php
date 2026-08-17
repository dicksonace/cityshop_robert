<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\SellerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreOversightController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $sellers = SellerProfile::with('user:id,name,email,mobile')
            ->whereHas('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('store_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $sellers->getCollection()->map(fn (SellerProfile $seller) => [
                'id' => $seller->id,
                'store_name' => $seller->displayName(),
                'slug' => $seller->slug,
                'status' => $seller->status?->value,
                'user' => $seller->user ? [
                    'id' => $seller->user->id,
                    'name' => $seller->user->name,
                    'mobile' => $seller->user->mobile,
                ] : null,
            ])->values(),
            'meta' => AdminJson::meta($sellers),
        ]);
    }

    public function show(Request $request, SellerProfile $seller): JsonResponse
    {
        $seller->load('user');
        $user = $seller->user;

        $query = Product::with(['images', 'category'])->where('seller_id', $user->id);
        $status = $request->string('status')->toString();
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif (in_array($status, ['approved', 'pending', 'rejected', 'draft'], true)) {
            $query->where('status', $status);
        }

        $products = $query->latest()->paginate(20);

        return response()->json([
            'seller' => [
                'id' => $seller->id,
                'store_name' => $seller->displayName(),
                'slug' => $seller->slug,
                'status' => $seller->status?->value,
                'user' => $seller->user ? ['id' => $seller->user->id, 'name' => $seller->user->name] : null,
            ],
            'stats' => $this->dashboard->stats($user),
            'data' => $products->getCollection()->map(function (Product $product) {
                $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'status' => $product->status?->value,
                    'quantity' => (int) $product->quantity,
                    'trashed' => $product->trashed(),
                    'image' => $image?->path ? Storage::disk('public')->url($image->path) : null,
                ];
            })->values(),
            'meta' => AdminJson::meta($products),
        ]);
    }

    public function hideProduct(SellerProfile $seller, Product $product): JsonResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);
        $product->update(['status' => ProductStatus::Draft]);

        return response()->json(['message' => 'Product hidden from the shop.']);
    }

    public function approveProduct(SellerProfile $seller, Product $product): JsonResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);
        $product->update(['status' => ProductStatus::Approved, 'rejection_reason' => null]);

        return response()->json(['message' => 'Product approved.']);
    }

    public function destroyProduct(SellerProfile $seller, Product $product): JsonResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);
        $product->delete();

        return response()->json(['message' => 'Product moved to trash.']);
    }

    public function restoreProduct(SellerProfile $seller, int $product): JsonResponse
    {
        $model = Product::withTrashed()
            ->whereKey($product)
            ->where('seller_id', $seller->user_id)
            ->firstOrFail();
        abort_unless($model->trashed(), 404);
        $model->restore();

        return response()->json(['message' => 'Product restored.']);
    }

    public function bulkProducts(Request $request, SellerProfile $seller): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:hide,delete,approve'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $products = Product::withTrashed()
            ->where('seller_id', $seller->user_id)
            ->whereIn('id', $validated['product_ids'])
            ->get();

        $count = 0;
        foreach ($products as $product) {
            if ($validated['action'] === 'delete') {
                if (! $product->trashed()) {
                    $product->delete();
                    $count++;
                }
                continue;
            }
            if ($product->trashed()) {
                continue;
            }
            match ($validated['action']) {
                'hide' => $product->update(['status' => ProductStatus::Draft]),
                'approve' => $product->update(['status' => ProductStatus::Approved, 'rejection_reason' => null]),
            };
            $count++;
        }

        return response()->json(['message' => "{$count} product(s) updated."]);
    }

    private function assertProductBelongsToSeller(SellerProfile $seller, Product $product): void
    {
        abort_unless(
            Product::withTrashed()->whereKey($product->id)->where('seller_id', $seller->user_id)->exists(),
            404,
        );
    }
}
