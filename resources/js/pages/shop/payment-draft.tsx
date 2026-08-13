import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, LoaderCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice } from '@/types/marketplace';

interface PaymentDraftProps {
    amount: number;
    paystackFee?: number;
    paystackCharge?: number;
    paymentMethod: string;
    shipping: {
        receiver_name?: string;
        city?: string;
        region?: string;
    };
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

export default function PaymentDraft({
    amount,
    paystackCharge,
    shipping,
    paystackPublicKey,
    paystackConfigured,
}: PaymentDraftProps) {
    const totalDue = paystackCharge && paystackCharge > 0 ? paystackCharge : amount;
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
            setError('Paystack is not configured.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('checkout.paystack-draft.initialize'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message ?? 'Payment initialization failed');

            const handler = window.PaystackPop?.setup({
                key: paystackPublicKey,
                email: data.email,
                amount: data.amount,
                currency: 'GHS',
                ref: data.reference,
                callback: () => router.visit(route('checkout.callback', { reference: data.reference })),
                onClose: () => setLoading(false),
            });
            handler?.openIframe();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Payment failed');
            setLoading(false);
        }
    }, [paystackConfigured, paystackPublicKey]);

    return (
        <ShopLayout>
            <Head title="Complete Payment" />
            <div className="mx-auto max-w-2xl px-4 py-12">
                <Link href={route('checkout.index')} className="text-sm text-orange-500 hover:underline">
                    &larr; Back to checkout
                </Link>
                <div className="mt-4 rounded-2xl bg-white p-8 shadow-sm">
                    <CreditCard className="mx-auto h-12 w-12 text-orange-500" />
                    <h1 className="mt-4 text-center text-2xl font-bold text-gray-900">Complete Payment</h1>
                    <p className="mt-2 text-center text-sm text-gray-500">
                        Your order is created only after payment succeeds. Closing Paystack will not place an order.
                    </p>
                    {(shipping.receiver_name || shipping.city) && (
                        <p className="mt-3 text-center text-xs text-gray-400">
                            Deliver to {shipping.receiver_name}
                            {shipping.city ? `, ${shipping.city}` : ''}
                            {shipping.region ? `, ${shipping.region}` : ''}
                        </p>
                    )}

                    <div className="mt-6 rounded-xl border border-orange-100 bg-orange-50 p-4">
                        <p className="font-semibold text-gray-900">CityShop secure payment</p>
                        <p className="mt-2 text-2xl font-bold text-orange-500">{formatPrice(totalDue)}</p>
                        <p className="mt-1 text-sm text-gray-500">Pay securely via Paystack.</p>
                        {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                        <Button
                            onClick={payWithPaystack}
                            disabled={loading}
                            className="mt-4 w-full bg-orange-500 hover:bg-orange-600"
                        >
                            {loading && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Pay via CityShop
                        </Button>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
