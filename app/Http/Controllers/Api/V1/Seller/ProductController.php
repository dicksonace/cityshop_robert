<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Services\CategorySpecService;
use App\Services\ProductAnalyticsService;
use App\Services\ProductVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['images', 'category'])
            ->withCount('reviews')
            ->where('seller_id', $request->user()->id);

        $status = $request->string('status')->toString();
        if ($status && in_array($status, ['approved', 'pending', 'rejected', 'draft'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'sold_out') {
            $query->where('quantity', 0)->where('is_preorder', false);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $sort = $request->string('sort', 'newest')->toString();
        match ($sort) {
            'oldest' => $query->oldest(),
            'price_asc' => $query->orderByRaw('COALESCE(discount_price, price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(discount_price, price) desc'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $products = $query->paginate($perPage);

        $base = Product::where('seller_id', $request->user()->id);

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => $this->serialize($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'counts' => [
                'all' => (clone $base)->count(),
                'approved' => (clone $base)->where('status', ProductStatus::Approved)->count(),
                'draft' => (clone $base)->where('status', ProductStatus::Draft)->count(),
                'pending' => (clone $base)->where('status', ProductStatus::Pending)->count(),
                'rejected' => (clone $base)->where('status', ProductStatus::Rejected)->count(),
                'sold_out' => (clone $base)->where('quantity', 0)->where('is_preorder', false)->count(),
            ],
            'categories' => Category::activeOrdered()->get()->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'spec_fields' => $c->specFields(),
            ])->values(),
            'can_create' => ! $request->user()->sellerProfile?->needsActivationPayment(),
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);
        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json(['data' => $this->serialize($product, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($blocked = $this->activationBlocked($request)) {
            return $blocked;
        }

        $validated = $this->validateListing($request, creating: true);
        $files = $this->uploadedImages($request);

        if (count($files) < 1) {
            return response()->json([
                'message' => 'Add at least one product photo.',
                'errors' => ['images' => ['Add at least one product photo.']],
            ], 422);
        }

        $product = null;
        DB::transaction(function () use ($validated, $files, $request, &$product) {
            $videoPath = null;
            $videoDuration = null;
            if ($request->hasFile('video')) {
                $request->validate([
                    'video' => ['file', 'mimes:mp4,webm,mov,qt,m4v,3gp,3gpp', 'max:51200'],
                    'video_duration' => ['nullable', 'integer', 'min:0', 'max:60'],
                ]);
                $videoPath = ProductVideoService::storeUploaded($request->file('video'));
                $videoDuration = $request->filled('video_duration') ? (int) $request->input('video_duration') : null;
            }

            $product = Product::create([
                ...$this->listingAttributes($validated, $request),
                'seller_id' => $request->user()->id,
                'status' => ProductStatus::Approved,
                'is_preorder' => false,
                'video_path' => $videoPath,
                'video_duration' => $videoDuration,
            ]);

            foreach ($files as $index => $image) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $image->store('products', 'public'),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        });

        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => 'Product published. It is now live in the shop.',
            'data' => $this->serialize($product, detailed: true),
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $validated = $this->validateListing($request, creating: false);

        if (isset($validated['discount_price']) && $validated['discount_price'] !== null) {
            $price = (float) ($validated['price'] ?? $product->price);
            if ((float) $validated['discount_price'] >= $price) {
                return response()->json([
                    'message' => 'Discount price must be less than the selling price.',
                    'errors' => ['discount_price' => ['Discount price must be less than the selling price.']],
                ], 422);
            }
        }

        $removeIds = collect($validated['remove_image_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        if ($removeIds !== []) {
            ProductImage::where('product_id', $product->id)
                ->whereIn('id', $removeIds)
                ->get()
                ->each->delete();
        }

        $attributes = $this->listingAttributes($validated, $request, $product);
        if ($attributes !== []) {
            $product->update($attributes);
        }

        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => 'Product updated.',
            'data' => $this->serialize($product, detailed: true),
        ]);
    }

    public function uploadImages(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $files = $this->uploadedImages($request);
        $currentCount = $product->images()->count();
        if ($currentCount + count($files) > 6) {
            return response()->json([
                'message' => 'Maximum 6 images allowed per product.',
                'errors' => ['images' => ['Maximum 6 images allowed per product.']],
            ], 422);
        }

        $startOrder = $product->images()->max('sort_order') ?? -1;
        foreach ($files as $index => $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image->store('products', 'public'),
                'is_primary' => $currentCount === 0 && $index === 0,
                'sort_order' => $startOrder + $index + 1,
            ]);
        }

        $first = $product->images()->orderBy('sort_order')->first();
        if ($first) {
            $product->images()->update(['is_primary' => false]);
            $first->update(['is_primary' => true]);
        }

        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => 'Photos added.',
            'data' => $this->serialize($product, detailed: true),
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:hide,delete,category'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        if ($validated['action'] === 'category' && empty($validated['category_id'])) {
            return response()->json([
                'message' => 'Pick a category for the selected products.',
                'errors' => ['category_id' => ['Pick a category for the selected products.']],
            ], 422);
        }

        $products = Product::where('seller_id', $request->user()->id)
            ->whereIn('id', $validated['product_ids'])
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products selected.'], 422);
        }

        $count = 0;
        foreach ($products as $product) {
            if ($validated['action'] === 'delete') {
                $this->permanentlyRemove($product);
                $count++;
                continue;
            }

            match ($validated['action']) {
                'hide' => $product->update(['status' => ProductStatus::Draft]),
                'category' => $product->update(['category_id' => $validated['category_id']]),
            };
            $count++;
        }

        return response()->json([
            'message' => $validated['action'] === 'delete'
                ? "{$count} product(s) deleted."
                : "{$count} product(s) updated.",
            'count' => $count,
        ]);
    }

    public function toggleVisibility(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        if ($product->status !== ProductStatus::Approved && ($blocked = $this->activationBlocked($request))) {
            return $blocked;
        }

        if (in_array($product->status, [ProductStatus::Draft, ProductStatus::Pending, ProductStatus::Rejected], true)) {
            $product->update([
                'status' => ProductStatus::Approved,
                'rejection_reason' => null,
            ]);
            $message = 'Product is now live in the shop.';
        } elseif ($product->status === ProductStatus::Approved) {
            $product->update(['status' => ProductStatus::Draft]);
            $message = 'Product hidden from your store.';
        } else {
            return response()->json(['message' => 'Only live or draft products can be toggled.'], 422);
        }

        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => $message,
            'data' => $this->serialize($product),
        ]);
    }

    public function duplicate(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        if ($blocked = $this->activationBlocked($request)) {
            return $blocked;
        }

        $product->load('images');
        $copy = $product->replicate(['slug', 'views', 'rating', 'review_count', 'cart_adds', 'wishlist_adds', 'purchase_count']);
        $copy->name = $product->name.' (Copy)';
        $copy->slug = Product::generateUniqueSlug($copy->name, $product->seller_id);
        $copy->status = ProductStatus::Draft;
        $copy->save();

        foreach ($product->images as $image) {
            ProductImage::create([
                'product_id' => $copy->id,
                'path' => $image->path,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ]);
        }

        $copy->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => 'Product duplicated. Review and publish when ready.',
            'data' => $this->serialize($copy, detailed: true),
        ], 201);
    }

    public function uploadVideo(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        if ($request->boolean('remove_video')) {
            if ($product->video_path) {
                Storage::disk('public')->delete($product->video_path);
            }
            $product->update(['video_path' => null, 'video_duration' => null]);
            $product->load(['images', 'category'])->loadCount('reviews');

            return response()->json([
                'message' => 'Product video removed.',
                'data' => $this->serialize($product, detailed: true),
            ]);
        }

        $request->validate([
            'video' => ['required', 'file', 'mimes:mp4,webm,mov,qt,m4v,3gp,3gpp', 'max:51200'],
            'video_duration' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        if ($product->video_path) {
            Storage::disk('public')->delete($product->video_path);
        }

        $product->update([
            'video_path' => ProductVideoService::storeUploaded($request->file('video')),
            'video_duration' => $request->filled('video_duration') ? (int) $request->input('video_duration') : null,
        ]);
        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'message' => 'Product video saved.',
            'data' => $this->serialize($product, detailed: true),
        ]);
    }

    public function analytics(Request $request, Product $product, ProductAnalyticsService $analytics): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);
        $product->load(['images', 'category'])->loadCount('reviews');

        return response()->json([
            'data' => $this->serialize($product, detailed: true),
            'stats' => $analytics->statsForProduct($product),
        ]);
    }

    public function reviews(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $reviews = Review::with('user')
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $review) => $this->serializeReview($review, $product))->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $this->permanentlyRemove($product);

        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $product, bool $detailed = false): array
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $fee = $product->delivery_fee !== null ? (float) $product->delivery_fee : null;
        $shippingType = $product->free_shipping
            ? 'free'
            : (($fee !== null && $fee > 0) ? 'paid' : 'buyer');

        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'discount_price' => $product->discount_price !== null ? (float) $product->discount_price : null,
            'quantity' => (int) $product->quantity,
            'status' => $product->status?->value ?? (string) $product->status,
            'is_live' => $product->status === ProductStatus::Approved,
            'image' => $this->publicUrl($image?->path),
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'review_count' => (int) ($product->reviews_count ?? $product->review_count ?? 0),
            'has_video' => filled($product->video_path),
            'created_at' => $product->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['description'] = $product->description;
            $payload['rejection_reason'] = $product->rejection_reason;
            $payload['video_url'] = $this->publicUrl($product->video_path);
            $payload['video_duration'] = $product->video_duration !== null ? (int) $product->video_duration : null;
            $payload['images'] = $product->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $this->publicUrl($img->path),
                'is_primary' => (bool) $img->is_primary,
            ])->values()->all();
            $payload['sku'] = $product->sku;
            $payload['brand'] = $product->brand;
            $payload['condition'] = $product->condition;
            $payload['wholesale_price'] = $product->wholesale_price !== null ? (float) $product->wholesale_price : null;
            $payload['minimum_order_quantity'] = $product->minimum_order_quantity !== null ? (int) $product->minimum_order_quantity : null;
            $payload['is_negotiable'] = (bool) $product->is_negotiable;
            $payload['low_stock_alert'] = $product->low_stock_alert !== null ? (int) $product->low_stock_alert : null;
            $payload['weight'] = $product->weight !== null ? (float) $product->weight : null;
            $payload['shipping_type'] = $shippingType;
            $payload['free_shipping'] = (bool) $product->free_shipping;
            $payload['delivery_fee'] = $fee;
            $payload['delivery_days'] = $product->delivery_days !== null ? (int) $product->delivery_days : null;
            $payload['cash_on_delivery'] = (bool) $product->cash_on_delivery;
            $payload['pickup_available'] = (bool) $product->pickup_available;
            $payload['ships_nationwide'] = (bool) $product->ships_nationwide;
            $payload['in_ghana'] = (bool) $product->in_ghana;
            $payload['specifications'] = $product->specifications ?? [];
            $payload['spec_fields'] = $product->category?->specFields() ?? [];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReview(Review $review, Product $product): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'seller_reply' => $review->seller_reply,
            'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
            'created_at' => $review->created_at?->toIso8601String(),
            'buyer_name' => $review->user?->name,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateListing(Request $request, bool $creating): array
    {
        $nameRule = $creating ? 'required' : 'sometimes';
        $priceRule = $creating ? 'required' : 'sometimes';
        $qtyRule = $creating ? 'required' : 'sometimes';
        $imageRules = $creating
            ? ['required', 'array', 'min:1', 'max:6']
            : ['nullable', 'array', 'max:6'];

        $validated = $request->validate([
            'name' => [$nameRule, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', 'in:new,used,refurbished'],
            'price' => [$priceRule, 'numeric', 'min:0'],
            'discount_price' => array_values(array_filter([
                'nullable',
                'numeric',
                'min:0',
                $creating ? 'lt:price' : null,
            ])),
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'is_negotiable' => ['sometimes', 'boolean'],
            'quantity' => [$qtyRule, 'integer', 'min:0'],
            'low_stock_alert' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_type' => ['nullable', 'in:free,paid,buyer'],
            'free_shipping' => ['sometimes', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'cash_on_delivery' => ['sometimes', 'boolean'],
            'pickup_available' => ['sometimes', 'boolean'],
            'ships_nationwide' => ['sometimes', 'boolean'],
            'in_ghana' => ['sometimes', 'boolean'],
            'specifications' => ['nullable', 'array'],
            'images' => $imageRules,
            'images.*' => ['image', 'max:5120'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
        ]);

        $shippingType = $request->input('shipping_type');
        if ($shippingType === 'paid') {
            $request->validate([
                'delivery_fee' => ['required', 'numeric', 'min:0.01'],
            ]);
            $validated['free_shipping'] = false;
            $validated['delivery_fee'] = $request->input('delivery_fee');
        } elseif ($shippingType === 'free') {
            $validated['free_shipping'] = true;
            $validated['delivery_fee'] = null;
        } elseif ($shippingType === 'buyer') {
            $validated['free_shipping'] = false;
            $validated['delivery_fee'] = null;
        } elseif (($validated['free_shipping'] ?? false) === true) {
            $validated['delivery_fee'] = null;
        }

        unset($validated['shipping_type']);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function listingAttributes(array $validated, Request $request, ?Product $product = null): array
    {
        $data = collect($validated)->except(['images', 'remove_image_ids', 'specifications'])->all();
        $data = $this->withListingDefaults($data, creating: $product === null);

        $categoryId = $validated['category_id'] ?? $product?->category_id;
        if ($request->exists('specifications') || array_key_exists('category_id', $validated)) {
            $data['specifications'] = $this->resolveSpecifications(
                $categoryId !== null ? (int) $categoryId : null,
                is_array($request->input('specifications')) ? $request->input('specifications') : [],
            );
        }

        return $data;
    }

    /**
     * NOT NULL product columns cannot be persisted as null — the mobile app
     * sends empty Condition as "" which Laravel converts to null and 500s.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withListingDefaults(array $data, bool $creating): array
    {
        $defaults = [
            'condition' => 'new',
            'low_stock_alert' => 5,
            'minimum_order_quantity' => 1,
        ];

        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if ($data[$key] === null || $data[$key] === '') {
                if ($creating) {
                    $data[$key] = $default;
                } else {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    /**
     * @return list<\Illuminate\Http\UploadedFile>
     */
    private function uploadedImages(Request $request): array
    {
        $files = $request->file('images') ?? [];
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        return array_values(array_filter($files));
    }

    private function resolveSpecifications(?int $categoryId, array $specs): ?array
    {
        if (! $categoryId) {
            return $specs ?: null;
        }

        $category = Category::find($categoryId);
        if (! $category) {
            return $specs ?: null;
        }

        $validated = CategorySpecService::validateSpecs($category->slug, $specs);

        return $validated ?: null;
    }

    private function permanentlyRemove(Product $product): void
    {
        if ($product->orderItems()->exists()) {
            $product->delete();

            return;
        }

        $product->forceDelete();
    }

    private function activationBlocked(Request $request): ?JsonResponse
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile?->needsActivationPayment()) {
            return null;
        }

        return response()->json([
            'message' => 'Pay your annual seller service fee to list products. You can still withdraw and recharge.',
            'needs_activation' => true,
        ], 403);
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }
}
