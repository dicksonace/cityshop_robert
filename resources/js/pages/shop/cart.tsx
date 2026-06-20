import { Head, Link, router } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { CartItem, formatPrice, productImageUrl } from '@/types/marketplace';

interface CartProps {
    items: CartItem[];
    subtotal: number;
}

export default function Cart({ items, subtotal }: CartProps) {
    const updateQuantity = (item: CartItem, quantity: number) => {
        if (quantity < 1) return;
        router.patch(route('cart.update', item.id), { quantity });
    };

    return (
        <ShopLayout>
            <Head title="Shopping Cart" />
            <div className="mx-auto max-w-4xl px-3 py-4 sm:px-4 sm:py-8">
                <h1 className="text-2xl font-bold text-gray-900">Shopping Cart</h1>

                {items.length === 0 ? (
                    <div className="mt-8 rounded-xl bg-white p-12 text-center shadow-sm">
                        <p className="text-gray-500">Your cart is empty.</p>
                        <Link href={route('home')} className="mt-4 inline-block text-orange-500 hover:underline">
                            Continue Shopping
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 space-y-4">
                        {items.map((item) => (
                            <div key={item.id} className="flex flex-col gap-3 rounded-xl bg-white p-3 shadow-sm sm:flex-row sm:gap-4 sm:p-4">
                                <img
                                    src={productImageUrl(item.product.images?.[0]?.path)}
                                    alt={item.product.name}
                                    className="mx-auto h-28 w-28 rounded-lg object-contain sm:mx-0 sm:h-24 sm:w-24"
                                />
                                <div className="flex min-w-0 flex-1 flex-col justify-between">
                                    <div>
                                        <Link href={route('products.show', item.product.slug)} className="line-clamp-2 font-medium text-gray-900 hover:text-orange-500">
                                            {item.product.name}
                                        </Link>
                                        <p className="text-lg font-bold text-orange-500">
                                            {formatPrice(item.product.discount_price ?? item.product.price)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <button type="button" onClick={() => updateQuantity(item, item.quantity - 1)} className="rounded border p-1">
                                            <Minus className="h-4 w-4" />
                                        </button>
                                        <span className="w-8 text-center">{item.quantity}</span>
                                        <button type="button" onClick={() => updateQuantity(item, item.quantity + 1)} className="rounded border p-1">
                                            <Plus className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => router.delete(route('cart.destroy', item.id))}
                                            className="ml-auto text-red-500 hover:text-red-600"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}

                        <div className="rounded-xl bg-white p-6 shadow-sm">
                            <div className="flex justify-between text-lg font-semibold">
                                <span>Subtotal</span>
                                <span className="text-orange-500">{formatPrice(subtotal)}</span>
                            </div>
                            <Link href={route('checkout.index')}>
                                <Button className="mt-4 w-full bg-orange-500 py-6 text-lg hover:bg-orange-600">Proceed to Checkout</Button>
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
