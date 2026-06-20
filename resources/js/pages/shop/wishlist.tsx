import { Head, Link, router } from '@inertiajs/react';
import { Heart, ShoppingBag } from 'lucide-react';

import ProductCard from '@/components/shop/product-card';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart } from '@/lib/shop-actions';
import { Product, WishlistItem } from '@/types/marketplace';

interface WishlistProps {
    items: WishlistItem[];
}

export default function Wishlist({ items }: WishlistProps) {
    const handleAddToCart = (productId: number) => {
        addProductToCart(productId);
    };

    const products: Product[] = items.map((item) => item.product);

    return (
        <ShopLayout>
            <Head title="My Wishlist" />
            <div className="mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-8">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                        <Heart className="h-5 w-5 fill-red-500 text-red-500" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">My Wishlist</h1>
                        <p className="text-sm text-gray-500">
                            {items.length} saved {items.length === 1 ? 'item' : 'items'}
                        </p>
                    </div>
                </div>

                {items.length === 0 ? (
                    <div className="mt-8 rounded-xl bg-white p-12 text-center shadow-sm">
                        <Heart className="mx-auto h-12 w-12 text-gray-200" />
                        <p className="mt-4 text-gray-500">You haven&apos;t saved any products yet.</p>
                        <Link href={route('home')} className="mt-4 inline-block text-orange-500 hover:underline">
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="mt-6 grid grid-cols-1 gap-3 min-[480px]:grid-cols-2 sm:gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            {products.map((product) => (
                                <ProductCard key={product.id} product={product} onAddToCart={handleAddToCart} />
                            ))}
                        </div>

                        <div className="mt-8 flex justify-center">
                            <Button asChild className="bg-orange-500 hover:bg-orange-600">
                                <Link href={route('home')}>
                                    <ShoppingBag className="mr-2 h-4 w-4" />
                                    Continue Shopping
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </ShopLayout>
    );
}
