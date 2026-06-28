<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\SellerDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreOversightController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $sellers = SellerProfile::with('user')
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
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/stores/index', [
            'sellers' => $sellers,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function show(Request $request, SellerProfile $seller): Response
    {
        $seller->load('user');
        $user = $seller->user;

        $query = Product::with(['images', 'category'])
            ->where('seller_id', $user->id);

        $status = $request->string('status')->toString();
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status && in_array($status, ['approved', 'pending', 'rejected', 'draft'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'sold_out') {
            $query->where('quantity', 0)->where('is_preorder', false);
        }

        $productSearch = $request->string('product_search')->trim()->toString();
        if ($productSearch !== '') {
            $query->where('name', 'like', "%{$productSearch}%");
        }

        $sort = $request->string('sort', 'newest')->toString();
        match ($sort) {
            'oldest' => $query->oldest(),
            'price_asc' => $query->orderByRaw('COALESCE(discount_price, price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(discount_price, price) desc'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        return Inertia::render('admin/stores/show', [
            'seller' => $seller,
            'stats' => $this->dashboard->stats($user),
            'storeHealth' => $this->dashboard->storeHealthScore($user),
            'storeUrl' => $seller->slug
                ? route('store.show', $seller->slug, absolute: true)
                : null,
            'products' => $products,
            'filters' => [
                'status' => $status ?: null,
                'product_search' => $productSearch ?: null,
                'sort' => $sort,
            ],
        ]);
    }

    public function hideProduct(SellerProfile $seller, Product $product): RedirectResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);

        $product->update(['status' => ProductStatus::Draft]);

        return back()->with('success', 'Product disabled and hidden from the shop.');
    }

    public function destroyProduct(SellerProfile $seller, Product $product): RedirectResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);

        $product->delete();

        return back()->with('success', 'Product moved to trash. Restore it from the Deleted filter if needed.');
    }

    public function restoreProduct(SellerProfile $seller, Product $product): RedirectResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);
        abort_unless($product->trashed(), 404);

        $product->restore();

        return back()->with('success', 'Product restored.');
    }

    public function approveProduct(SellerProfile $seller, Product $product): RedirectResponse
    {
        $this->assertProductBelongsToSeller($seller, $product);

        $product->update([
            'status' => ProductStatus::Approved,
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Product approved and live in the shop.');
    }

    public function bulkProducts(Request $request, SellerProfile $seller): RedirectResponse
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

        if ($products->isEmpty()) {
            return back()->with('error', 'No products selected.');
        }

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
                'approve' => $product->update([
                    'status' => ProductStatus::Approved,
                    'rejection_reason' => null,
                ]),
            };
            $count++;
        }

        $label = match ($validated['action']) {
            'hide' => 'disabled',
            'approve' => 'approved',
            default => 'moved to trash',
        };

        return back()->with('success', "{$count} product(s) {$label}.");
    }

    private function assertProductBelongsToSeller(SellerProfile $seller, Product $product): void
    {
        abort_unless(
            Product::withTrashed()
                ->whereKey($product->id)
                ->where('seller_id', $seller->user_id)
                ->exists(),
            404,
        );
    }
}
