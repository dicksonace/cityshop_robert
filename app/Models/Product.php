<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'specifications',
        'sku',
        'brand',
        'condition',
        'price',
        'discount_price',
        'wholesale_price',
        'minimum_order_quantity',
        'is_negotiable',
        'quantity',
        'low_stock_alert',
        'weight',
        'status',
        'is_preorder',
        'free_shipping',
        'delivery_fee',
        'delivery_days',
        'cash_on_delivery',
        'pickup_available',
        'ships_nationwide',
        'in_ghana',
        'rating',
        'review_count',
        'views',
        'cart_adds',
        'wishlist_adds',
        'purchase_count',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_negotiable' => 'boolean',
            'cash_on_delivery' => 'boolean',
            'pickup_available' => 'boolean',
            'ships_nationwide' => 'boolean',
            'weight' => 'decimal:2',
            'status' => ProductStatus::class,
            'is_preorder' => 'boolean',
            'free_shipping' => 'boolean',
            'in_ghana' => 'boolean',
            'rating' => 'decimal:2',
            'specifications' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (! $product->slug) {
                $product->slug = static::generateUniqueSlug($product->name, $product->seller_id);
            }
        });
    }

    public static function generateUniqueSlug(string $name, int $sellerId): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (static::where('seller_id', $sellerId)->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first()
            ?? $this->images()->first();
    }

    public function effectivePrice(): float
    {
        return (float) ($this->discount_price ?? $this->price);
    }

    public function isInStock(): bool
    {
        return $this->quantity > 0 || $this->is_preorder;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statDaily(): HasMany
    {
        return $this->hasMany(ProductStatDaily::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ProductStatus::Approved);
    }

    public function scopeVisibleInShop($query)
    {
        return $query->where('status', ProductStatus::Approved)
            ->whereHas('seller', function ($q) {
                $q->whereHas('sellerProfile', fn ($sq) => $sq->where('status', SellerStatus::Approved));
            });
    }

    public function isVisibleInShop(): bool
    {
        if ($this->status !== ProductStatus::Approved) {
            return false;
        }

        $profile = $this->seller?->sellerProfile;

        return $profile && $profile->status === SellerStatus::Approved;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
