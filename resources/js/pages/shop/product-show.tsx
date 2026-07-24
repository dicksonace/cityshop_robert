import ShopLayout from '@/layouts/shop-layout';
import { recordRecentView } from '@/lib/recent-views';
import { addProductToCart, scrollToReviews } from '@/lib/shop-actions';
import { formatPrice, Paginated, Product, ProductReview } from '@/types/marketplace';
import { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { MapPin, MessageSquare, Package, ShoppingBag, Store, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

import ProductCard from '@/components/shop/product-card';
import ProductEngagementStats from '@/components/shop/product-engagement-stats';
import ProductImageGallery from '@/components/shop/product-image-gallery';
import ProductReviews from '@/components/shop/product-reviews';
import ProductSellerInfo from '@/components/shop/product-seller-info';
import ProductShareButton from '@/components/shop/product-share-button';
import ProductSpecifications from '@/components/shop/product-specifications';
import RatingDisplay from '@/components/shop/rating-display';
import MessageSellerButton from '@/components/shop/message-seller-button';
import WishlistButton from '@/components/shop/wishlist-button';
import { Button } from '@/components/ui/button';

interface ProductShowProps {
    product: Product;
    related: Product[];
    reviews: Paginated<ProductReview>;
    reviewable?: { order_id: number; order_item_id: number } | null;
}

export default function ProductShow({ product, related, reviews, reviewable }: ProductShowProps) {
    const { auth, canShop = true } = usePage<SharedData>().props;
    const price = product.discount_price ?? product.price;
    const [likes, setLikes] = useState(product.wishlist_adds ?? 0);

    useEffect(() => {
        setLikes(product.wishlist_adds ?? 0);
    }, [product.wishlist_adds]);

    useEffect(() => {
        recordRecentView({
            id: product.id,
            category_id: product.category?.id ?? null,
            category: product.category ?? null,
        });
    }, [product.id, product.category]);

    const handleAddToCart = () => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        if (!canShop) {
            return;
        }
        if (!product.is_preorder && product.quantity < 1) {
            return;
        }
        addProductToCart(product.id);
    };

    const handleRelatedAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        if (!canShop) {
            return;
        }
        addProductToCart(productId);
    };

    return (
        <ShopLayout>
            <Head title={product.name} />
            <div className="mx-auto w-full max-w-7xl px-3 py-4 sm:px-4 sm:py-8">
                <div className="grid w-full grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-8">
                    <ProductImageGallery
                        images={product.images ?? []}
                        productName={product.name}
                        videoPath={product.video_path}
                        videoDuration={product.video_duration}
                        className="w-full min-w-0"
                    />

                    <div className="w-full min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            {product.category?.icon && (
                                <span className="flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                    <span className="text-base">{product.category.icon}</span>
                                    {product.category.name}
                                </span>
                            )}
                            {product.free_shipping && <span className="rounded-md bg-green-500 px-2 py-1 text-xs font-bold text-white">FREE DELIVERY</span>}
                            {!product.free_shipping && product.delivery_fee != null && Number(product.delivery_fee) > 0 && (
                                <span className="rounded-md bg-blue-500 px-2 py-1 text-xs font-bold text-white">
                                    DELIVERY {formatPrice(Number(product.delivery_fee))}
                                </span>
                            )}
                            {product.ships_nationwide && (
                                <span className="rounded-md bg-indigo-500 px-2 py-1 text-xs font-bold text-white">SHIPS NATIONWIDE</span>
                            )}
                            {product.pickup_available && (
                                <span className="rounded-md bg-slate-700 px-2 py-1 text-xs font-bold text-white">PICKUP</span>
                            )}
                            {product.is_negotiable && (
                                <span className="rounded-md bg-amber-500 px-2 py-1 text-xs font-bold text-white">NEGOTIABLE</span>
                            )}
                            {product.cash_on_delivery && (
                                <span className="rounded-md bg-teal-600 px-2 py-1 text-xs font-bold text-white">CASH ON DELIVERY</span>
                            )}
                        </div>

                        <h1 className="mt-4 text-2xl font-bold text-gray-900 md:text-3xl">{product.name}</h1>

                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <RatingDisplay rating={product.rating} reviewCount={product.review_count} size="md" />
                            <button
                                type="button"
                                onClick={scrollToReviews}
                                className="inline-flex items-center gap-1 text-sm font-medium text-orange-500 hover:underline"
                            >
                                <MessageSquare className="h-4 w-4" />
                                {reviewable ? 'Write a review' : 'See reviews & rate'}
                            </button>
                        </div>

                        <ProductEngagementStats
                            views={product.views}
                            likes={likes}
                            size="md"
                            className="mt-3"
                        />

                        <p className="mt-4 text-3xl font-bold text-orange-500">{formatPrice(price)}</p>
                        {product.discount_price && (
                            <p className="text-sm text-gray-400 line-through">{formatPrice(product.price)}</p>
                        )}

                        <div className="mt-5 overflow-hidden rounded-[1.5rem] border border-slate-100 bg-gradient-to-b from-slate-50/90 to-white shadow-sm">
                            <div className="border-b border-slate-100 px-4 py-4">
                                {product.description ? (
                                    <p className="text-[15px] leading-relaxed text-slate-700">{product.description}</p>
                                ) : (
                                    <p className="text-sm text-slate-400">No description provided.</p>
                                )}

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {product.brand && (
                                        <span className="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                            Brand · {product.brand}
                                        </span>
                                    )}
                                    <span
                                        className={
                                            product.quantity > 0
                                                ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100'
                                                : 'inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-100'
                                        }
                                    >
                                        <Package className="h-3.5 w-3.5" />
                                        {product.quantity > 0 ? `${product.quantity} in stock` : 'Out of stock'}
                                    </span>
                                </div>
                            </div>

                            <div className="space-y-2.5 p-3 sm:p-4">
                                <p className="px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                    Delivery & pickup
                                </p>

                                {product.free_shipping ? (
                                    <div className="flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/90 px-3.5 py-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                            <Truck className="h-4 w-4" strokeWidth={2} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-emerald-800">Free delivery</p>
                                            <p className="text-xs text-emerald-700/80">Seller covers shipping to you</p>
                                        </div>
                                    </div>
                                ) : product.delivery_fee != null && Number(product.delivery_fee) > 0 ? (
                                    <div className="flex items-start gap-3 rounded-2xl border border-sky-100 bg-sky-50/90 px-3.5 py-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                                            <Truck className="h-4 w-4" strokeWidth={2} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-sky-800">
                                                Paid delivery: {formatPrice(Number(product.delivery_fee))}
                                            </p>
                                            {product.delivery_days ? (
                                                <p className="text-xs text-sky-700/80">
                                                    Delivery Time: {product.delivery_days}day{product.delivery_days === 1 ? '' : 's'}
                                                </p>
                                            ) : (
                                                <p className="text-xs text-sky-700/80">Delivery fee added at checkout</p>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-2xl border border-slate-100 bg-white px-3.5 py-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                                            <Truck className="h-4 w-4" strokeWidth={2} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-700">Delivery arranged with seller</p>
                                            <p className="text-xs text-slate-500">Chat to agree on delivery details</p>
                                        </div>
                                    </div>
                                )}

                                {product.ships_nationwide && (
                                    <div className="flex items-start gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/90 px-3.5 py-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                            <MapPin className="h-4 w-4" strokeWidth={2} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-indigo-800">Ships nationwide across Ghana</p>
                                            <p className="text-xs text-indigo-700/80">Available beyond the seller&apos;s local area</p>
                                        </div>
                                    </div>
                                )}

                                {product.pickup_available && (
                                    <div className="flex items-start gap-3 rounded-2xl border border-orange-200 bg-gradient-to-r from-orange-50 via-amber-50 to-white px-3.5 py-3 shadow-sm">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white shadow-sm shadow-orange-200">
                                            <Store className="h-4 w-4" strokeWidth={2.25} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-orange-900">Pickup available from the seller shop</p>
                                            <p className="mt-0.5 text-xs text-orange-800/80">
                                                Collect in person
                                                {product.seller?.seller_profile?.store_name
                                                    ? ` at ${product.seller.seller_profile.store_name}`
                                                    : ' from the seller'}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {product.cash_on_delivery && (
                                    <p className="px-1 text-sm font-medium text-teal-700">Cash on delivery available</p>
                                )}
                                {product.is_negotiable && (
                                    <p className="px-1 text-sm font-medium text-amber-700">Price is negotiable — chat the seller</p>
                                )}
                            </div>
                        </div>

                        {product.seller?.seller_profile && (
                            <ProductSellerInfo
                                seller={product.seller}
                                productId={product.id}
                                showChatButton={false}
                                currentUserId={auth.user?.id}
                            />
                        )}

                        <div className="mt-6 space-y-3">
                            {!canShop && auth.user ? (
                                <>
                                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        <p className="font-semibold">Seller accounts cannot buy products</p>
                                        <p className="mt-1 text-amber-800/90">
                                            Use a buyer account to shop. You can still manage your store from Seller Centre.
                                        </p>
                                        <Link
                                            href={route('seller.dashboard')}
                                            className="mt-2 inline-flex text-sm font-semibold text-orange-600 hover:underline"
                                        >
                                            Go to Seller Centre →
                                        </Link>
                                    </div>
                                    {product.seller && auth.user.id !== product.seller.id && (
                                        <MessageSellerButton
                                            sellerId={product.seller.id}
                                            productId={product.id}
                                            label="Chat Seller"
                                            className="w-full py-3 text-sm"
                                        />
                                    )}
                                </>
                            ) : (
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        onClick={handleAddToCart}
                                        disabled={!product.is_preorder && product.quantity < 1}
                                        className="w-full bg-orange-500 py-4 text-sm hover:bg-orange-600 disabled:opacity-50 sm:py-6 sm:text-lg"
                                    >
                                        <ShoppingBag className="mr-1.5 h-4 w-4 sm:mr-2 sm:h-5 sm:w-5" />
                                        {!product.is_preorder && product.quantity < 1 ? 'Out of stock' : 'Add to Cart'}
                                    </Button>
                                    {product.seller && auth.user?.id !== product.seller.id ? (
                                        <MessageSellerButton
                                            sellerId={product.seller.id}
                                            productId={product.id}
                                            label="Chat Seller"
                                            className="w-full py-4 text-sm sm:py-6 sm:text-lg"
                                        />
                                    ) : (
                                        <div />
                                    )}
                                </div>
                            )}
                            <div className="flex items-center justify-center gap-2 sm:justify-start">
                                <WishlistButton
                                    productId={product.id}
                                    size="md"
                                    onOptimisticToggle={(adding) =>
                                        setLikes((count) => Math.max(0, count + (adding ? 1 : -1)))
                                    }
                                />
                                <ProductShareButton productName={product.name} size="md" />
                            </div>
                        </div>
                    </div>
                </div>

                <ProductSpecifications
                    category={product.category}
                    specifications={product.specifications}
                />

                <ProductReviews
                    productSlug={product.slug}
                    reviews={reviews}
                    reviewable={reviewable}
                />

                {related.length > 0 && (
                    <section className="mt-12">
                        <h2 className="text-xl font-bold text-gray-900">Related Products</h2>
                        <div className="mt-4 grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-4">
                            {related.map((p) => (
                                <ProductCard key={p.id} product={p} onAddToCart={handleRelatedAddToCart} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </ShopLayout>
    );
}
