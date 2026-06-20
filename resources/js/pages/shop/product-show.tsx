import { Head, router, usePage } from '@inertiajs/react';
import { MessageSquare, ShoppingBag } from 'lucide-react';

import ProductCard from '@/components/shop/product-card';
import MessageSellerButton from '@/components/shop/message-seller-button';
import ProductImageGallery from '@/components/shop/product-image-gallery';
import ProductReviews from '@/components/shop/product-reviews';
import ProductSpecifications from '@/components/shop/product-specifications';
import RatingDisplay from '@/components/shop/rating-display';
import SellerStoreLink from '@/components/shop/seller-store-link';
import WishlistButton from '@/components/shop/wishlist-button';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart, scrollToReviews } from '@/lib/shop-actions';
import { formatPrice, Paginated, Product, ProductReview } from '@/types/marketplace';
import { SharedData } from '@/types';

interface ProductShowProps {
    product: Product;
    related: Product[];
    reviews: Paginated<ProductReview>;
    reviewable?: { order_id: number; order_item_id: number } | null;
}

export default function ProductShow({ product, related, reviews, reviewable }: ProductShowProps) {
    const { auth } = usePage<SharedData>().props;
    const price = product.discount_price ?? product.price;

    const handleAddToCart = () => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(product.id);
    };

    const handleRelatedAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    return (
        <ShopLayout>
            <Head title={product.name} />
            <div className="mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-8">
                <div className="grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <ProductImageGallery images={product.images ?? []} productName={product.name} />

                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            {product.category?.icon && (
                                <span className="flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                    <span className="text-base">{product.category.icon}</span>
                                    {product.category.name}
                                </span>
                            )}
                            {product.free_shipping && <span className="rounded-md bg-green-500 px-2 py-1 text-xs font-bold text-white">FREE DELIVERY</span>}
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

                        <p className="mt-4 text-3xl font-bold text-orange-500">{formatPrice(price)}</p>
                        {product.discount_price && (
                            <p className="text-sm text-gray-400 line-through">{formatPrice(product.price)}</p>
                        )}

                        <p className="mt-4 text-gray-600">{product.description}</p>

                        <div className="mt-4 text-sm text-gray-500">
                            {product.brand && <p>Brand: {product.brand}</p>}
                            <p>Stock: {product.quantity > 0 ? `${product.quantity} available` : 'Out of stock'}</p>
                            <p className="mt-1 text-gray-400">Delivered by the seller</p>
                        </div>

                        {product.seller?.seller_profile && (
                            <div className="mt-4">
                                <SellerStoreLink
                                    profile={product.seller.seller_profile}
                                    sellerName={product.seller.name}
                                    variant="card"
                                />
                            </div>
                        )}

                        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                            <Button onClick={handleAddToCart} className="w-full bg-orange-500 py-5 text-base hover:bg-orange-600 sm:w-auto sm:py-6 sm:text-lg sm:px-12">
                                <ShoppingBag className="mr-2 h-5 w-5" />
                                Add to Cart
                            </Button>
                            {product.seller && auth.user?.id !== product.seller.id && (
                                <MessageSellerButton
                                    sellerId={product.seller.id}
                                    productId={product.id}
                                    variant="outline"
                                    className="w-full py-5 text-base sm:w-auto sm:py-6 sm:text-lg"
                                />
                            )}
                            <div className="flex justify-center sm:justify-start">
                                <WishlistButton productId={product.id} size="md" />
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
                        <div className="mt-4 grid grid-cols-1 gap-3 min-[480px]:grid-cols-2 sm:gap-4 md:grid-cols-4">
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
