import { Head, router } from '@inertiajs/react';
import { CreditCard, LoaderCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, Order } from '@/types/marketplace';

interface PaymentProps {
    order: Order;
    paystackPublicKey: string;
    paystackConfigured: boolean;
}

declare global {
    interface Window {
        PaystackPop?: {
            setup: (options: Record<string, unknown>) => { openIframe: () => void };
        };
    }
}

export default function Payment({ order, paystackPublicKey, paystackConfigured }: PaymentProps) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v1/inline.js';
        script.async = true;
        document.body.appendChild(script);
        return () => {
            document.body.removeChild(script);
        };
    }, []);

    const payWithPaystack = useCallback(async () => {
        if (!paystackConfigured) {
            setError('Paystack is not configured. Add PAYSTACK_PUBLIC_KEY and PAYSTACK_SECRET_KEY to your .env file.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('checkout.initialize', order.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message ?? 'Payment initialization failed');
            }

            const handler = window.PaystackPop?.setup({
                key: paystackPublicKey,
                email: data.email,
                amount: Math.round(order.total * 100),
                currency: 'GHS',
                ref: data.reference,
                callback: () => {
                    router.visit(route('checkout.callback', { reference: data.reference }));
                },
                onClose: () => setLoading(false),
            });

            handler?.openIframe();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Payment failed');
        } finally {
            setLoading(false);
        }
    }, [order, paystackConfigured, paystackPublicKey]);

    return (
        <ShopLayout>
            <Head title="Complete Payment" />
            <div className="mx-auto max-w-lg px-4 py-12">
                <div className="rounded-2xl bg-white p-8 shadow-sm text-center">
                    <CreditCard className="mx-auto h-12 w-12 text-orange-500" />
                    <h1 className="mt-4 text-2xl font-bold text-gray-900">Complete Payment</h1>
                    <p className="mt-2 text-gray-500">Order {order.order_number}</p>
                    <p className="mt-4 text-3xl font-bold text-orange-500">{formatPrice(order.total)}</p>

                    <p className="mt-2 text-sm text-gray-500 capitalize">Pay with {order.payment_method === 'momo' ? 'Mobile Money' : 'Card'} via Paystack</p>

                    {error && <p className="mt-4 text-sm text-red-600">{error}</p>}

                    <Button
                        onClick={payWithPaystack}
                        disabled={loading}
                        className="mt-6 w-full bg-orange-500 py-6 text-lg hover:bg-orange-600"
                    >
                        {loading && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        Pay Now
                    </Button>

                    {!paystackConfigured && (
                        <p className="mt-4 text-xs text-amber-600">
                            Add your Paystack keys to .env to enable payments.
                        </p>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
