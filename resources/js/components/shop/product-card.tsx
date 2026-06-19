import { Link } from '@inertiajs/react';
import { Plus, ShoppingBag, Truck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import RatingDisplay from '@/components/shop/rating-display';
import SellerStoreLink from '@/components/shop/seller-store-link';
import WishlistButton from '@/components/shop/wishlist-button';
import { cn } from '@/lib/utils';
import { formatPrice, Product, productImageUrl } from '@/types/marketplace';

interface ProductCardProps {
    product: Product;
    onAddToCart?: (productId: number) => void;
    variant?: 'grid' | 'list';
}

export default function ProductCard({ product, onAddToCart, variant = 'grid' }: ProductCardProps) {
    const price = product.discount_price ?? product.price;
    const hasDiscount = product.discount_price && product.discount_price < product.price;
    const discountPct = hasDiscount ? Math.round((1 - product.discount_price! / product.price) * 100) : 0;
    const image = product.images?.[0];
    const sellerName = product.seller?.seller_profile?.business_name ?? product.seller?.name;

    if (variant === 'list') {
        return (
            <div className="group flex gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:border-orange-100 hover:shadow-md">
                <Link href={route('products.show', product.slug)} className="relative shrink-0">
                    <div className="flex h-36 w-36 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 p-3">
                        <img src={productImageUrl(image?.path)} alt={product.name} className="max-h-full max-w-full object-contain transition-transform group-hover:scale-105" />
                    </div>
                    {hasDiscount && (
                        <span className="absolute top-2 left-2 rounded-md bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">-{discountPct}%</span>
                    )}
                </Link>
                <div className="flex min-w-0 flex-1 flex-col justify-between">
                    <div>
                        <Link href={route('products.show', product.slug)}>
                            <h3 className="line-clamp-2 text-base font-semibold text-gray-900 hover:text-orange-500">{product.name}</h3>
                        </Link>
                        {sellerName && (
                            <p className="mt-0.5 text-xs text-gray-400">
                                Sold by{' '}
                                <SellerStoreLink profile={product.seller?.seller_profile} sellerName={sellerName} className="text-xs" />
                            </p>
                        )}
                        <div className="mt-2 flex items-center gap-2">
                            <RatingDisplay rating={product.rating} reviewCount={product.review_count} />
                            {product.free_shipping && (
                            <span className="flex items-center gap-0.5 text-xs text-green-600"><Truck className="h-3 w-3" /> Free delivery</span>
                        )}
                        </div>
                    </div>
                    <div className="flex items-end justify-between">
                        <div>
                            <p className="text-xl font-bold text-orange-500">{formatPrice(price)}</p>
                            {hasDiscount && <p className="text-sm text-gray-400 line-through">{formatPrice(product.price)}</p>}
                        </div>
                        {onAddToCart && (
                            <Button onClick={() => onAddToCart(product.id)} className="bg-orange-500 hover:bg-orange-600">
                                <ShoppingBag className="mr-2 h-4 w-4" /> Add to Cart
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-orange-200 hover:shadow-lg hover:shadow-orange-100/50">
            <Link href={route('products.show', product.slug)} className="block">
                <div className="relative flex aspect-square items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 via-white to-orange-50/30 p-5">
                    <div className="pointer-events-none absolute top-3 left-3 z-20 flex flex-col gap-1">
                        {hasDiscount && (
                            <span className="rounded-lg bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">-{discountPct}%</span>
                        )}
                    </div>
                    <div className="pointer-events-none absolute top-3 right-3 z-20 flex flex-col items-end gap-1">
                        {product.free_shipping && (
                            <span className="rounded-lg bg-emerald-500 px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase shadow-sm">Free Delivery</span>
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

            <div className="flex flex-1 flex-col p-4 pt-3">
                {product.category && (
                    <span className="mb-1 text-[10px] font-semibold tracking-wider text-blue-500 uppercase">{product.category.name}</span>
                )}
                <Link href={route('products.show', product.slug)}>
                    <h3 className="line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-gray-900 transition-colors group-hover:text-orange-500">
                        {product.name}
                    </h3>
                </Link>

                <RatingDisplay rating={product.rating} reviewCount={product.review_count} className="mt-1.5" />

                {product.seller?.seller_profile && (
                    <div className="mt-1.5">
                        <span className="text-xs text-gray-400">Sold by </span>
                        <SellerStoreLink
                            profile={product.seller.seller_profile}
                            sellerName={product.seller.name}
                            className="text-xs font-medium"
                        />
                    </div>
                )}

                <div className="mt-auto flex items-end justify-between pt-3">
                    <div>
                        <p className="text-lg font-bold text-orange-500">{formatPrice(price)}</p>
                        {hasDiscount && <p className="text-xs text-gray-400 line-through">{formatPrice(product.price)}</p>}
                    </div>
                    <div className="flex gap-1">
                        <WishlistButton productId={product.id} />
                        {onAddToCart && (
                            <Button
                                size="icon"
                                className="h-8 w-8 rounded-lg bg-gradient-to-br from-orange-500 to-orange-600 shadow-sm hover:from-orange-600 hover:to-orange-700"
                                onClick={() => onAddToCart(product.id)}
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

