import { Link, usePage } from '@inertiajs/react';
import { Plus, ShoppingBag, Truck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import RatingDisplay from '@/components/shop/rating-display';
import SellerStoreLink from '@/components/shop/seller-store-link';
import WishlistButton from '@/components/shop/wishlist-button';
import { formatPrice, Product, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface ProductCardProps {
    product: Product;
    onAddToCart?: (productId: number) => void;
    variant?: 'grid' | 'list';
}

export default function ProductCard({ product, onAddToCart, variant = 'grid' }: ProductCardProps) {
    const { canShop = true } = usePage<SharedData>().props;
    const price = product.discount_price ?? product.price;
    const hasDiscount = product.discount_price && product.discount_price < product.price;
    const discountPct = hasDiscount ? Math.round((1 - product.discount_price! / product.price) * 100) : 0;
    const image = product.images?.[0];
    const sellerName = product.seller?.seller_profile?.business_name ?? product.seller?.name;
    const showAdd = Boolean(onAddToCart) && canShop;

    if (variant === 'list') {
        return (
            <div className="group flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm transition-all hover:border-orange-100 hover:shadow-md sm:flex-row sm:gap-4 sm:p-4">
                <Link href={route('products.show', product.slug)} className="relative shrink-0 self-center sm:self-auto">
                    <div className="flex h-28 w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 p-3 sm:h-36 sm:w-36">
                        <img src={productImageUrl(image?.path)} alt={product.name} className="max-h-full max-w-full object-contain transition-transform group-hover:scale-105" />
                    </div>
                    {hasDiscount && (
                        <span className="absolute top-2 left-2 rounded-md bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">-{discountPct}%</span>
                    )}
                </Link>
                <div className="flex min-w-0 flex-1 flex-col justify-between">
                    <div>
                        <Link href={route('products.show', product.slug)}>
                            <h3 className="line-clamp-2 text-sm font-semibold text-gray-900 hover:text-orange-500 sm:text-base">{product.name}</h3>
                        </Link>
                        {sellerName && (
                            <p className="mt-0.5 truncate text-xs text-gray-400">
                                Sold by{' '}
                                <SellerStoreLink profile={product.seller?.seller_profile} sellerName={sellerName} className="text-xs" />
                            </p>
                        )}
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <RatingDisplay rating={product.rating} reviewCount={product.review_count} compact />
                            {product.free_shipping && (
                                <span className="flex items-center gap-0.5 text-xs text-green-600"><Truck className="h-3 w-3" /> Free delivery</span>
                            )}
                            {product.ships_nationwide && (
                                <span className="text-xs text-indigo-600">Ships nationwide</span>
                            )}
                        </div>
                    </div>
                    <div className="mt-3 flex items-end justify-between gap-2 sm:mt-0">
                        <div>
                            <p className="text-lg font-bold text-orange-500 sm:text-xl">{formatPrice(price)}</p>
                            {hasDiscount && <p className="text-sm text-gray-400 line-through">{formatPrice(product.price)}</p>}
                        </div>
                        {showAdd && (
                            <Button onClick={() => onAddToCart?.(product.id)} size="sm" className="bg-orange-500 hover:bg-orange-600 sm:size-default">
                                <ShoppingBag className="h-4 w-4 sm:mr-2" />
                                <span className="hidden sm:inline">Add to Cart</span>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="group relative flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-lg hover:shadow-orange-100/50 sm:rounded-2xl sm:hover:-translate-y-1">
            <div className="relative">
                <Link href={route('products.show', product.slug)} className="block">
                    <div className="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 via-white to-orange-50/30 p-3 sm:aspect-square sm:p-5">
                        <div className="pointer-events-none absolute top-2 left-2 z-20 sm:top-3 sm:left-3">
                            {hasDiscount && (
                                <span className="rounded-md bg-red-500 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm sm:rounded-lg sm:px-2 sm:text-[10px]">-{discountPct}%</span>
                            )}
                        </div>
                        <div className="pointer-events-none absolute bottom-2 left-2 z-20 sm:bottom-3 sm:left-3">
                            {product.free_shipping && (
                                <span className="inline-flex items-center gap-0.5 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm sm:px-2 sm:text-[10px]">
                                    <Truck className="h-3 w-3" /> Free
                                </span>
                            )}
                        </div>
                        <div className="absolute inset-0 z-0 bg-[radial-gradient(circle_at_30%_20%,rgba(251,146,60,0.08),transparent_50%)]" />
                        <img
                            src={productImageUrl(image?.path)}
                            alt={product.name}
                            className="relative z-10 max-h-full max-w-full object-contain transition-transform duration-500 group-hover:scale-110"
                            loading="lazy"
                        />
                    </div>
                </Link>
                <div className="absolute top-2 right-2 z-30 sm:top-3 sm:right-3">
                    <WishlistButton
                        productId={product.id}
                        className="h-8 w-8 rounded-full border-white/80 bg-white/95 shadow-sm backdrop-blur-sm hover:bg-white"
                    />
                </div>
            </div>

            <div className="flex flex-1 flex-col p-2.5 sm:p-4 sm:pt-3">
                {product.category && (
                    <span className="mb-0.5 truncate text-[9px] font-semibold tracking-wider text-blue-500 uppercase sm:mb-1 sm:text-[10px]">{product.category.name}</span>
                )}
                <Link href={route('products.show', product.slug)}>
                    <h3 className="line-clamp-2 text-xs font-semibold leading-snug text-gray-900 transition-colors group-hover:text-orange-500 sm:text-sm">
                        {product.name}
                    </h3>
                </Link>

                <RatingDisplay rating={product.rating} reviewCount={product.review_count} compact className="mt-1 sm:mt-1.5" />

                {product.seller?.seller_profile && (
                    <div className="mt-1 hidden truncate sm:block">
                        <span className="text-xs text-gray-400">Sold by </span>
                        <SellerStoreLink
                            profile={product.seller.seller_profile}
                            sellerName={product.seller.name}
                            className="text-xs font-medium"
                        />
                    </div>
                )}

                <div className="mt-auto flex items-end justify-between gap-1 pt-2 sm:gap-2 sm:pt-3">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-bold text-orange-500 sm:text-lg">{formatPrice(price)}</p>
                        {hasDiscount && <p className="text-[10px] text-gray-400 line-through sm:text-xs">{formatPrice(product.price)}</p>}
                    </div>
                    {showAdd && (
                        <Button
                            size="icon"
                            className="h-8 w-8 shrink-0 rounded-full bg-gradient-to-br from-orange-500 to-orange-600 shadow-md hover:from-orange-600 hover:to-orange-700 sm:h-9 sm:w-9"
                            onClick={() => onAddToCart?.(product.id)}
                            aria-label="Add to cart"
                        >
                            <Plus className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
