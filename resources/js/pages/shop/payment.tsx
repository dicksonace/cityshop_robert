import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, LoaderCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import DirectPaymentCard from '@/components/shop/direct-payment-card';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, Order } from '@/types/marketplace';

interface CheckoutData {
    id: number;
    checkout_number: string;
    total: number;
    payment_status: string;
    orders: (Order & {
        payment_channel: string;
        seller_payment_method?: {
            account_name: string;
            account_number?: string;
            network?: string;
            bank_name?: string;
            type?: string;
            instructions?: string;
        };
    })[];
}

interface PaymentProps {
    checkout: CheckoutData;
    marketplaceTotal: number;
    paystackFee?: number;
    paystackCharge?: number;
    directOrders: CheckoutData['orders'];
    paystackPublicKey: string;
    paystackConfigured: boolean;
    flutterwaveConfigured?: boolean;
    korapayConfigured?: boolean;
    junipayConfigured?: boolean;
}

declare global {
    interface Window {
        PaystackPop?: {
            setup: (options: Record<string, unknown>) => { openIframe: () => void };
        };
    }
}

export default function Payment({
    checkout,
    marketplaceTotal,
    paystackCharge,
    directOrders,
    paystackPublicKey,
    paystackConfigured,
    flutterwaveConfigured = false,
    korapayConfigured = false,
    junipayConfigured = false,
}: PaymentProps) {
    const totalDue = paystackCharge && paystackCharge > 0 ? paystackCharge : marketplaceTotal;
    const gatewayOptions = (
        [
            { id: 'junipay', label: 'Junipay', on: junipayConfigured },
            { id: 'flutterwave', label: 'Flutterwave', on: flutterwaveConfigured },
            { id: 'korapay', label: 'Korapay', on: korapayConfigured },
            { id: 'paystack', label: 'Paystack', on: paystackConfigured },
        ] as const
    ).filter((option) => option.on);
    const onlineAvailable = gatewayOptions.length > 0;
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [gateway, setGateway] = useState<'paystack' | 'flutterwave' | 'korapay' | 'junipay'>(gatewayOptions[0]?.id ?? 'paystack');

    useEffect(() => {
        if (!paystackConfigured) return;
        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v1/inline.js';
        script.async = true;
        document.body.appendChild(script);
        return () => {
            document.body.removeChild(script);
        };
    }, [paystackConfigured]);

    useEffect(() => {
        if (junipayConfigured) setGateway('junipay');
        else if (flutterwaveConfigured) setGateway('flutterwave');
        else if (korapayConfigured) setGateway('korapay');
        else if (paystackConfigured) setGateway('paystack');
    }, [paystackConfigured, flutterwaveConfigured, korapayConfigured, junipayConfigured]);

    const payWithPaystack = useCallback(async () => {
        if (!paystackConfigured) {
            setError('Online Paystack payment is temporarily disabled. Please use manual MoMo / bank payment.');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('checkout.initialize', checkout.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message ?? 'Payment initialization failed');

            const handler = window.PaystackPop?.setup({
                key: paystackPublicKey,
                email: data.email,
                amount: Math.round(data.amount * 100),
                currency: 'GHS',
                ref: data.reference,
                callback: () => router.visit(route('checkout.callback', { reference: data.reference })),
                onClose: () => setLoading(false),
            });
            handler?.openIframe();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Payment failed');
        } finally {
            setLoading(false);
        }
    }, [checkout.id, paystackConfigured, paystackPublicKey]);

    const payHosted = useCallback(async (name: 'flutterwave' | 'korapay' | 'junipay') => {
        const enabled = { flutterwave: flutterwaveConfigured, korapay: korapayConfigured, junipay: junipayConfigured };
        if (!enabled[name]) {
            setError(`${name.charAt(0).toUpperCase()}${name.slice(1)} payment is temporarily disabled.`);
            return;
        }

        setLoading(true);
        setError('');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route(`checkout.${name}.initialize`, checkout.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message ?? 'Payment initialization failed');
            if (!data.authorization_url) throw new Error('No payment link returned.');
            window.location.href = data.authorization_url;
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Payment failed');
            setLoading(false);
        }
    }, [checkout.id, flutterwaveConfigured, korapayConfigured, junipayConfigured]);

    const pay = () => {
        if (gateway !== 'paystack') return payHosted(gateway);
        return payWithPaystack();
    };

    return (
        <ShopLayout>
            <Head title="Complete Payment" />
            <div className="mx-auto max-w-2xl px-4 py-12">
                <Link
                    href={route('checkouts.show', checkout.id)}
                    className="text-sm text-orange-500 hover:underline"
                >
                    &larr; Back to purchase {checkout.checkout_number}
                </Link>
                <div className="mt-4 rounded-2xl bg-white p-8 shadow-sm">
                    <CreditCard className="mx-auto h-12 w-12 text-orange-500" />
                    <h1 className="mt-4 text-center text-2xl font-bold text-gray-900">Complete Payment</h1>
                    <p className="mt-2 text-center text-gray-500">Checkout {checkout.checkout_number}</p>

                    {marketplaceTotal > 0 && (
                        <div className="mt-6 rounded-xl border border-orange-100 bg-orange-50 p-4">
                            <p className="font-semibold text-gray-900">CityShop payment</p>
                            <p className="mt-2 text-2xl font-bold text-orange-500">{formatPrice(totalDue)}</p>
                            {onlineAvailable ? (
                                <>
                                    {gatewayOptions.length > 1 && (
                                        <div className={`mt-3 grid gap-2 ${gatewayOptions.length > 3 ? 'grid-cols-2 sm:grid-cols-4' : gatewayOptions.length > 2 ? 'grid-cols-3' : 'grid-cols-2'}`}>
                                            {gatewayOptions.map((option) => (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() => setGateway(option.id)}
                                                    className={`rounded-lg border px-3 py-2 text-sm font-semibold ${
                                                        gateway === option.id
                                                            ? 'border-orange-500 bg-white text-orange-700'
                                                            : 'border-transparent bg-white/50 text-gray-600'
                                                    }`}
                                                >
                                                    {option.label}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    <p className="mt-1 text-sm text-gray-500">
                                        Pay securely via {gatewayOptions.find((option) => option.id === gateway)?.label ?? 'Paystack'} for
                                        marketplace sellers.
                                    </p>
                                    {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                                    <Button
                                        onClick={pay}
                                        disabled={loading}
                                        className="mt-4 w-full bg-orange-500 hover:bg-orange-600"
                                    >
                                        {loading && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                        Pay via CityShop
                                    </Button>
                                </>
                            ) : (
                                <p className="mt-2 text-sm text-amber-800">
                                    Online payment is temporarily disabled. Use manual MoMo / bank payment where
                                    available, or try again later.
                                </p>
                            )}
                        </div>
                    )}

                    {directOrders.length > 0 && (
                        <div className="mt-6 space-y-4">
                            <h2 className="font-semibold text-gray-900">Pay sellers directly</h2>
                            {directOrders.map((order) => (
                                <DirectPaymentCard key={order.id} order={order} />
                            ))}
                        </div>
                    )}

                    {marketplaceTotal <= 0 && directOrders.length === 0 && (
                        <p className="mt-6 text-center text-gray-500">No payment required.</p>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
