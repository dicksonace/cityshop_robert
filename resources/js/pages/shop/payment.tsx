import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, LoaderCircle } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

import DirectPaymentDetails, { DIRECT_PAYMENT_NOTE } from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, Order, productImageUrl } from '@/types/marketplace';

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
            instructions?: string;
        };
    })[];
}

interface PaymentProps {
    checkout: CheckoutData;
    marketplaceTotal: number;
    directOrders: CheckoutData['orders'];
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

export default function Payment({ checkout, marketplaceTotal, directOrders, paystackPublicKey, paystackConfigured }: PaymentProps) {
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

    return (
        <ShopLayout>
            <Head title="Complete Payment" />
            <div className="mx-auto max-w-2xl px-4 py-12">
                <div className="rounded-2xl bg-white p-8 shadow-sm">
                    <CreditCard className="mx-auto h-12 w-12 text-orange-500" />
                    <h1 className="mt-4 text-center text-2xl font-bold text-gray-900">Complete Payment</h1>
                    <p className="mt-2 text-center text-gray-500">Checkout {checkout.checkout_number}</p>

                    {marketplaceTotal > 0 && (
                        <div className="mt-6 rounded-xl border border-orange-100 bg-orange-50 p-4">
                            <p className="font-semibold text-gray-900">CityShop payment</p>
                            <p className="mt-1 text-2xl font-bold text-orange-500">{formatPrice(marketplaceTotal)}</p>
                            <p className="mt-1 text-sm text-gray-500">Pay securely via Paystack for marketplace sellers.</p>
                            {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                            <Button onClick={payWithPaystack} disabled={loading} className="mt-4 w-full bg-orange-500 hover:bg-orange-600">
                                {loading && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Pay via CityShop
                            </Button>
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

function DirectPaymentCard({ order }: { order: PaymentProps['directOrders'][0] & {
    direct_payment_reference?: string | null;
    direct_payment_proof_path?: string | null;
    direct_payment_rejection_reason?: string | null;
} }) {
    const method = order.seller_payment_method;
    const { data, setData, post, processing, errors } = useForm<{
        reference: string;
        proof: File | null;
    }>({
        reference: order.direct_payment_reference ?? '',
        proof: null,
    });
    const [proofPreview, setProofPreview] = useState<string | null>(null);

    const accountNumber = method?.account_number ?? '';
    const isBank = Boolean(method?.bank_name && !method?.network);

    useEffect(() => {
        if (!data.proof) {
            setProofPreview(null);
            return;
        }
        const url = URL.createObjectURL(data.proof);
        setProofPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.proof]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('orders.direct-payment', order.id), { forceFormData: true });
    };

    const existingProof = order.direct_payment_proof_path
        ? productImageUrl(order.direct_payment_proof_path)
        : null;

    return (
        <div className="rounded-xl border border-gray-100 p-4">
            <div className="flex justify-between gap-2">
                <p className="font-medium">Order {order.order_number}</p>
                <p className="font-bold text-orange-500">{formatPrice(order.total)}</p>
            </div>
            {method?.network && (
                <p className="mt-1 text-xs text-gray-500">{method.network} Mobile Money</p>
            )}
            {method?.bank_name && (
                <p className="mt-1 text-xs text-gray-500">{method.bank_name}</p>
            )}

            {method && accountNumber && (
                <DirectPaymentDetails
                    className="mt-4"
                    accountNumber={accountNumber}
                    accountName={method.account_name}
                    isBank={isBank}
                    hint={`Send ${formatPrice(order.total)} to the number above. In the MoMo reference / reason field, paste ${DIRECT_PAYMENT_NOTE} so the seller can match your payment.${method.instructions ? ` ${method.instructions}` : ''}`}
                />
            )}

            {order.payment_status !== 'paid' && (
                <form onSubmit={submit} className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                    {order.direct_payment_rejection_reason && !order.direct_payment_reference && (
                        <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <p className="font-medium">Seller rejected your payment claim</p>
                            <p className="mt-1">{order.direct_payment_rejection_reason}</p>
                            <p className="mt-1 text-xs">Submit a valid transaction reference or screenshot again.</p>
                        </div>
                    )}
                    <div>
                        <Label htmlFor={`ref-${order.id}`}>Transaction ID from MoMo SMS</Label>
                        <Input
                            id={`ref-${order.id}`}
                            placeholder="From MoMo or bank SMS"
                            value={data.reference}
                            onChange={(e) => setData('reference', e.target.value)}
                            required
                            className="mt-1"
                        />
                        {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                    </div>
                    <div>
                        <Label htmlFor={`proof-${order.id}`}>Payment screenshot (optional)</Label>
                        <Input
                            id={`proof-${order.id}`}
                            type="file"
                            accept="image/*"
                            className="mt-1"
                            onChange={(e) => setData('proof', e.target.files?.[0] ?? null)}
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Upload MoMo/bank receipt if you have one — helps the seller confirm faster.
                        </p>
                        {errors.proof && <p className="mt-1 text-xs text-red-600">{errors.proof}</p>}
                        {(proofPreview || existingProof) && (
                            <a
                                href={proofPreview ?? existingProof ?? '#'}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-block"
                            >
                                <img
                                    src={proofPreview ?? existingProof ?? ''}
                                    alt="Payment proof"
                                    className="max-h-36 rounded-lg border border-gray-200 object-contain"
                                />
                            </a>
                        )}
                    </div>
                    <Button type="submit" disabled={processing} variant="outline" className="w-full sm:w-auto">
                        {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        {order.direct_payment_reference ? 'Update payment details' : "I've paid"}
                    </Button>
                    {order.direct_payment_reference && (
                        <p className="text-xs text-amber-700">Waiting for the seller to confirm your payment.</p>
                    )}
                </form>
            )}
            {order.payment_status === 'paid' && <p className="mt-2 text-sm text-green-600">Payment confirmed</p>}
        </div>
    );
}
