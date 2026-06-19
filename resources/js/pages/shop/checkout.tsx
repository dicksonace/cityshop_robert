import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { CartItem, formatPrice, productImageUrl } from '@/types/marketplace';
import { User } from '@/types';

interface CheckoutProps {
    items: CartItem[];
    subtotal: number;
    user: User;
}

export default function Checkout({ items, subtotal, user }: CheckoutProps) {
    const { data, setData, post, processing, errors } = useForm({
        receiver_name: user.name || '',
        receiver_phone: (user.mobile as string) || '',
        region: (user.region as string) || '',
        city: (user.city as string) || '',
        digital_address: (user.digital_address as string) || '',
        delivery_notes: '',
        payment_method: 'momo',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    return (
        <ShopLayout>
            <Head title="Checkout" />
            <div className="mx-auto max-w-4xl px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900">Checkout</h1>

                <form onSubmit={submit} className="mt-6 grid gap-8 lg:grid-cols-2">
                    <div className="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                        <h2 className="font-semibold text-gray-900">Delivery Details</h2>
                        <p className="text-sm text-gray-500">Sellers deliver directly to your address across Ghana.</p>
                        <div>
                            <Label>Receiver Name</Label>
                            <Input value={data.receiver_name} onChange={(e) => setData('receiver_name', e.target.value)} required className="mt-1" />
                            <InputError message={errors.receiver_name} />
                        </div>
                        <div>
                            <Label>Phone Number</Label>
                            <Input value={data.receiver_phone} onChange={(e) => setData('receiver_phone', e.target.value)} required className="mt-1" />
                            <InputError message={errors.receiver_phone} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Region</Label>
                                <Input value={data.region} onChange={(e) => setData('region', e.target.value)} required className="mt-1" />
                                <InputError message={errors.region} />
                            </div>
                            <div>
                                <Label>City</Label>
                                <Input value={data.city} onChange={(e) => setData('city', e.target.value)} required className="mt-1" />
                                <InputError message={errors.city} />
                            </div>
                        </div>
                        <div>
                            <Label>Digital Address</Label>
                            <Input value={data.digital_address} onChange={(e) => setData('digital_address', e.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Delivery Notes</Label>
                            <Input value={data.delivery_notes} onChange={(e) => setData('delivery_notes', e.target.value)} className="mt-1" />
                        </div>

                        <h2 className="pt-4 font-semibold text-gray-900">Payment Method</h2>
                        <div className="space-y-2">
                            {[
                                { value: 'momo', label: 'MTN MoMo' },
                                { value: 'card', label: 'Visa / Mastercard' },
                                { value: 'cash', label: 'Cash on Delivery' },
                            ].map((method) => (
                                <label key={method.value} className="flex items-center gap-2 rounded-lg border p-3 cursor-pointer hover:bg-gray-50">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value={method.value}
                                        checked={data.payment_method === method.value}
                                        onChange={() => setData('payment_method', method.value)}
                                    />
                                    {method.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-sm">
                        <h2 className="font-semibold text-gray-900">Order Summary</h2>
                        <div className="mt-4 space-y-3">
                            {items.map((item) => (
                                <div key={item.id} className="flex gap-3">
                                    <img src={productImageUrl(item.product.images?.[0]?.path)} alt="" className="h-12 w-12 rounded object-contain" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium line-clamp-1">{item.product.name}</p>
                                        <p className="text-xs text-gray-500">Qty: {item.quantity}</p>
                                    </div>
                                    <p className="text-sm font-medium">{formatPrice((item.product.discount_price ?? item.product.price) * item.quantity)}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 border-t pt-4">
                            <div className="flex justify-between font-bold text-lg">
                                <span>Total</span>
                                <span className="text-orange-500">{formatPrice(subtotal)}</span>
                            </div>
                        </div>
                        <Button type="submit" disabled={processing} className="mt-6 w-full bg-orange-500 py-6 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            {data.payment_method === 'cash' ? 'Place Order' : 'Continue to Payment'}
                        </Button>
                    </div>
                </form>
            </div>
        </ShopLayout>
    );
}
