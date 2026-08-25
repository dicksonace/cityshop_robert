<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Services\CategorySpecService;
use App\Services\ProductAnalyticsService;
use App\Services\ProductVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
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

        $products = $query->paginate(12)->withQueryString();

        return Inertia::render('seller/products/index', [
            'products' => $products,
            'filters' => [
                'status' => $status ?: null,
                'search' => $search ?: null,
                'sort' => $sort,
            ],
            'categories' => Category::activeOrdered()->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->activationRedirect($request)) {
            return $redirect;
        }

        return Inertia::render('seller/products/create', [
            'categories' => Category::activeOrdered()->get(),
            'profile' => auth()->user()->sellerProfile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->activationRedirect($request)) {
            return $redirect;
        }

        // Oversized multipart uploads can empty the whole request (PHP post_max_size).
        if ($request->server('CONTENT_LENGTH') && empty($request->all()) && empty($request->allFiles())) {
            return back()->withErrors([
                'images' => 'Upload was too large and could not be received. Use smaller photos (under 5MB each) and a video under 50MB / 1 minute. If this keeps happening, ask support to raise the server upload limit.',
                'video' => 'The video file was too large for the server to accept. Use a clip under 50MB and 1 minute.',
            ]);
        }

        $videoUploadError = $this->videoUploadError($request);
        if ($videoUploadError) {
            return back()->withErrors(['video' => $videoUploadError])->withInput();
        }

        $validated = $this->validateProduct($request, true);

        $files = $request->file('images') ?? [];
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        $imageCountError = $this->imageUploadCountError($request, count($files));

        if ($imageCountError) {
            return back()->withErrors(['images' => $imageCountError])->withInput();
        }

        if (count($files) < 1) {
            return back()->withErrors([
                'images' => 'Add at least one product photo, then publish again.',
            ])->withInput();
        }

        $videoError = $this->videoDurationError($request);
        if ($videoError) {
            return back()->withErrors(['video' => $videoError])->withInput();
        }

        $specifications = $this->resolveSpecifications($validated['category_id'] ?? null, $request->input('specifications', []));

        DB::transaction(function () use ($validated, $files, $request, $specifications, &$product) {
            $videoPath = null;
            $videoDuration = null;

            if ($request->hasFile('video')) {
                $videoPath = ProductVideoService::storeUploaded($request->file('video'));
                $videoDuration = (int) $request->input('video_duration');
            }

            $product = Product::create([
                ...$this->withListingDefaults(
                    collect($validated)->except(['images', 'image_count', 'video', 'video_duration', 'remove_video'])->toArray(),
                    creating: true,
                ),
                'specifications' => $specifications,
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

        if ($product && filled($product->video_path)) {
            ProductVideoService::scheduleProductWebCompat($product);
        }

        return redirect()->route('seller.products.index')
            ->with('success', 'Product published successfully. It is now live in the shop.');
    }

    public function edit(Request $request, Product $product): Response
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        return Inertia::render('seller/products/edit', [
            'product' => $product->load(['images', 'category']),
            'categories' => Category::activeOrdered()->get(),
            'profile' => $request->user()->sellerProfile,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $videoUploadError = $this->videoUploadError($request);
        if ($videoUploadError) {
            return back()->withErrors(['video' => $videoUploadError])->withInput();
        }

        $validated = $this->validateProduct($request, false);

        $videoError = $this->videoDurationError($request);
        if ($videoError) {
            return back()->withErrors(['video' => $videoError])->withInput();
        }

        if ($request->filled('remove_images')) {
            ProductImage::where('product_id', $product->id)
                ->whereIn('id', $request->input('remove_images'))
                ->get()
                ->each->delete();
        }

        $product->refresh();
        $currentCount = $product->images()->count();
        $newCount = $request->file('images') ? count($request->file('images')) : 0;

        if ($currentCount + $newCount > Product::MAX_IMAGES) {
            return back()->withErrors(['images' => 'Maximum '.Product::MAX_IMAGES.' images allowed per product.']);
        }

        if ($request->file('images')) {
            $newFiles = $request->file('images');
            $imageCountError = $this->imageUploadCountError($request, count($newFiles));

            if ($imageCountError) {
                return back()->withErrors(['images' => $imageCountError])->withInput();
            }

            $startOrder = $product->images()->max('sort_order') ?? -1;
            foreach ($newFiles as $index => $image) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $image->store('products', 'public'),
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                    'sort_order' => $startOrder + $index + 1,
                ]);
            }
        }

        $first = $product->images()->orderBy('sort_order')->first();
        if ($first) {
            $product->images()->update(['is_primary' => false]);
            $first->update(['is_primary' => true]);
        }

        $specifications = $this->resolveSpecifications($validated['category_id'] ?? null, $request->input('specifications', []));

        $nextStatus = $product->status === ProductStatus::Draft
            ? ProductStatus::Draft
            : ProductStatus::Approved;

        $videoUpdates = $this->resolveVideoUpdates($request, $product);

        $product->update([
            ...$this->withListingDefaults(
                collect($validated)->except(['images', 'image_count', 'remove_images', 'video', 'video_duration', 'remove_video'])->toArray(),
                creating: false,
            ),
            'specifications' => $specifications,
            'status' => $nextStatus,
            'is_preorder' => false,
            'rejection_reason' => null,
            ...$videoUpdates,
        ]);

        if (array_key_exists('video_path', $videoUpdates) && filled($product->fresh()->video_path)) {
            ProductVideoService::scheduleProductWebCompat($product->fresh());
        }

        $message = $nextStatus === ProductStatus::Approved
            ? 'Product updated successfully. It is live in the shop.'
            : 'Product updated. It is still hidden — publish it when you are ready.';

        return redirect()->route('seller.products.index')
            ->with('success', $message);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $this->permanentlyRemove($product);

        return back()->with('success', 'Product deleted permanently.');
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        if ($redirect = $this->activationRedirect($request)) {
            return $redirect;
        }

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

        return redirect()->route('seller.products.edit', $copy)
            ->with('success', 'Product duplicated. Review and publish when ready.');
    }

    public function toggleVisibility(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        if ($product->status !== ProductStatus::Approved && ($redirect = $this->activationRedirect($request))) {
            return $redirect;
        }

        if ($product->status === ProductStatus::Draft || $product->status === ProductStatus::Pending || $product->status === ProductStatus::Rejected) {
            $product->update([
                'status' => ProductStatus::Approved,
                'rejection_reason' => null,
            ]);
            $message = 'Product is now live in the shop.';
        } elseif ($product->status === ProductStatus::Approved) {
            $product->update(['status' => ProductStatus::Draft]);
            $message = 'Product hidden from your store.';
        } else {
            return back()->with('error', 'Only live or draft products can be toggled.');
        }

        return back()->with('success', $message);
    }

    public function analytics(Request $request, Product $product, ProductAnalyticsService $analytics): Response
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        return Inertia::render('seller/products/analytics', [
            'product' => $product->load(['images', 'category']),
            'stats' => $analytics->statsForProduct($product),
        ]);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:hide,delete,category'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $products = Product::where('seller_id', $request->user()->id)
            ->whereIn('id', $validated['product_ids'])
            ->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'No products selected.');
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

        $message = $validated['action'] === 'delete'
            ? "{$count} product(s) deleted permanently."
            : "{$count} product(s) updated.";

        return back()->with('success', $message);
    }

    public function reviews(Request $request, Product $product): Response
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $reviews = Review::with(['user', 'order'])
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seller/products/reviews', [
            'product' => $product->only(['id', 'name', 'slug', 'rating', 'review_count']),
            'reviews' => $reviews,
        ]);
    }

    /**
     * Seller delete is permanent (no trash / restore). Products that already
     * appear on orders stay soft-deleted so order history is kept.
     */
    private function permanentlyRemove(Product $product): void
    {
        if ($product->orderItems()->exists()) {
            $product->delete();

            return;
        }

        $product->forceDelete();
    }

    /**
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

    private function validateProduct(Request $request, bool $creating): array
    {
        $imageRules = $creating
            ? ['required', 'array', 'min:1', 'max:'.Product::MAX_IMAGES]
            : ['nullable', 'array', 'max:'.Product::MAX_IMAGES];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', 'in:new,used,refurbished'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'is_negotiable' => ['boolean'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_alert' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_type' => ['nullable', 'in:free,paid,buyer'],
            'free_shipping' => ['boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'cash_on_delivery' => ['boolean'],
            'pickup_available' => ['boolean'],
            'ships_nationwide' => ['boolean'],
            'in_ghana' => ['boolean'],
            'specifications' => ['nullable', 'array'],
            'images' => $imageRules,
            'images.*' => ['image', 'max:5120'],
            // 0 is valid on update when no new photos are uploaded (existing images remain).
            'image_count' => ['nullable', 'integer', 'min:0', 'max:'.Product::MAX_IMAGES],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'exists:product_images,id'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov,qt,m4v,3gp,3gpp', 'max:51200'],
            'video_duration' => ['nullable', 'integer', 'min:0', 'max:60'],
            'remove_video' => ['nullable', 'boolean'],
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
        } elseif ($validated['free_shipping'] ?? false) {
            $validated['delivery_fee'] = null;
        }

        unset($validated['shipping_type']);

        return $validated;
    }

    private function videoUploadError(Request $request): ?string
    {
        $uploaded = $request->files->get('video');

        if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && ! $uploaded->isValid()) {
            return match ($uploaded->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The video file was too large for the server to accept. Use a clip under 50MB and 1 minute.',
                UPLOAD_ERR_PARTIAL => 'The video upload was interrupted. Please try again.',
                default => 'Video upload failed. Please try another file.',
            };
        }

        return null;
    }

    private function videoDurationError(Request $request): ?string
    {
        if (! $request->hasFile('video')) {
            return null;
        }

        $file = $request->file('video');
        if ($file && ! $file->isValid()) {
            return $this->videoUploadError($request) ?? 'Video upload failed. Please try another file.';
        }

        $duration = $request->input('video_duration');

        if ($duration === null || $duration === '') {
            return 'Could not verify video length. Please try another file.';
        }

        if ((int) $duration < 0 || (int) $duration > 60) {
            return 'Product video must be 1 minute or less.';
        }

        return null;
    }

    /**
     * @return array{video_path?: string|null, video_duration?: int|null}
     */
    private function resolveVideoUpdates(Request $request, Product $product): array
    {
        if ($request->hasFile('video')) {
            try {
                $newPath = ProductVideoService::storeUploaded($request->file('video'));
            } catch (\Throwable $e) {
                report($e);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'video' => ['Could not save this video. Try another clip (MP4 under 1 minute).'],
                ]);
            }

            $oldPath = $product->video_path;
            if ($oldPath && $oldPath !== $newPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return [
                'video_path' => $newPath,
                'video_duration' => (int) $request->input('video_duration'),
            ];
        }

        if ($request->boolean('remove_video')) {
            if ($product->video_path) {
                Storage::disk('public')->delete($product->video_path);
            }

            return [
                'video_path' => null,
                'video_duration' => null,
            ];
        }

        return [];
    }

    private function imageUploadCountError(Request $request, int $receivedCount): ?string
    {
        $expectedCount = (int) $request->input('image_count', 0);

        if ($expectedCount > 0 && $receivedCount < $expectedCount) {
            return "Only {$receivedCount} of {$expectedCount} images were received. Try again with smaller photos (under 5MB each).";
        }

        return null;
    }

    private function resolveSpecifications(?int $categoryId, array $specs): ?array
    {
        if (! $categoryId) {
            return null;
        }

        $category = Category::find($categoryId);
        if (! $category) {
            return null;
        }

        $validated = CategorySpecService::validateSpecs($category->slug, $specs);

        return $validated ?: null;
    }

    private function activationRedirect(Request $request): ?RedirectResponse
    {
        $profile = $request->user()?->sellerProfile;
        if (! $profile?->needsActivationPayment()) {
            return null;
        }

        return redirect()->route('seller.activation.show')->with(
            'error',
            'Pay your annual seller service fee to list products. Buyers cannot see your store until you pay. You can still withdraw and recharge.',
        );
    }
}
