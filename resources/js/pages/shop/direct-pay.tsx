import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CreditCard, LoaderCircle } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

import DocumentUploadField from '@/components/forms/document-upload-field';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import {
    clearPaymentReference,
    loadPaymentReference,
    savePaymentReference,
} from '@/lib/checkout-draft';
import { isBankPaymentMethod, bankPaymentTitle } from '@/lib/payment-method-display';
import { formatPrice, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface DirectPayPackage {
    seller_id: number;
    seller_name: string;
    store_slug?: string | null;
    subtotal: number;
    shipping_cost: number;
    shipping_label: string;
    package_total: number;
    items: {
        id: number;
        quantity: number;
        product: { name: string; images?: { path: string }[] };
    }[];
    payment_method: {
        id: number;
        type?: string | null;
        account_name: string;
        account_number?: string | null;
        network?: string | null;
        bank_name?: string | null;
        instructions?: string | null;
    } | null;
}

interface DirectPayProps {
    packages: DirectPayPackage[];
    shipping?: {
        receiver_name?: string;
        receiver_phone?: string;
        region?: string;
        city?: string;
    } | null;
}

export default function DirectPay({ packages, shipping }: DirectPayProps) {
    const { flash } = usePage<SharedData>().props;

    return (
        <ShopLayout>
            <Head title="Pay seller" />
            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-12">
                <Link href={route('checkout.index')} className="text-sm text-orange-500 hover:underline">
                    &larr; Back to checkout
                </Link>

                <div className="mt-4 rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                    <CreditCard className="mx-auto h-12 w-12 text-orange-500" />
                    <h1 className="mt-4 text-center text-2xl font-bold text-gray-900">Pay seller directly</h1>
                    <p className="mt-2 text-center text-sm text-gray-500">
                        No order is created until you upload proof or a transaction ID. You can go back anytime.
                    </p>
                    {shipping?.receiver_name && (
                        <p className="mt-3 text-center text-xs text-gray-400">
                            Ship to {shipping.receiver_name}
                            {shipping.city ? ` · ${shipping.city}` : ''}
                            {shipping.region ? `, ${shipping.region}` : ''}
                        </p>
                    )}

                    {(flash.success || flash.error) && (
                        <div
                            className={`mt-4 rounded-lg px-3 py-2 text-sm ${
                                flash.error
                                    ? 'border border-red-100 bg-red-50 text-red-700'
                                    : 'border border-emerald-100 bg-emerald-50 text-emerald-800'
                            }`}
                        >
                            {flash.error ?? flash.success}
                        </div>
                    )}

                    <div className="mt-6 space-y-4">
                        {packages.map((pkg) => (
                            <DirectPayPackageCard key={pkg.seller_id} pkg={pkg} />
                        ))}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}

function DirectPayPackageCard({ pkg }: { pkg: DirectPayPackage }) {
    const method = pkg.payment_method;
    const draftKey = -pkg.seller_id;
    const { data, setData, post, processing, errors } = useForm<{
        reference: string;
        proof: File | null;
    }>({
        reference: loadPaymentReference(draftKey) ?? '',
        proof: null,
    });

    useEffect(() => {
        savePaymentReference(draftKey, data.reference);
    }, [draftKey, data.reference]);

    const accountNumber = method?.account_number ?? '';
    const isBank = isBankPaymentMethod(method);
    const bankTitle = bankPaymentTitle(method);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('checkout.direct-pay.submit', pkg.seller_id), {
            forceFormData: true,
            onSuccess: () => clearPaymentReference(draftKey),
        });
    };

    return (
        <div className="rounded-xl border border-gray-100 p-4">
            <div className="flex justify-between gap-2">
                <div>
                    <p className="font-medium text-gray-900">{pkg.seller_name}</p>
                    <p className="text-xs text-gray-500">
                        {pkg.items.length} item{pkg.items.length === 1 ? '' : 's'}
                        {pkg.shipping_cost > 0 ? ` · ${pkg.shipping_label} ${formatPrice(pkg.shipping_cost)}` : ''}
                    </p>
                </div>
                <p className="font-bold text-orange-500">{formatPrice(pkg.package_total)}</p>
            </div>

            <ul className="mt-3 space-y-2">
                {pkg.items.map((item) => (
                    <li key={item.id} className="flex gap-2 text-sm text-gray-600">
                        <img
                            src={productImageUrl(item.product.images?.[0]?.path)}
                            alt=""
                            className="h-10 w-10 rounded object-contain"
                        />
                        <span className="line-clamp-2">
                            {item.product.name} · Qty {item.quantity}
                        </span>
                    </li>
                ))}
            </ul>

            {isBank && <p className="mt-2 text-xs text-gray-500">{bankTitle}</p>}

            {method && accountNumber && (
                <DirectPaymentDetails
                    className="mt-4"
                    accountNumber={accountNumber}
                    accountName={method.account_name}
                    network={isBank ? null : method.network}
                    isBank={isBank}
                    bankName={method.bank_name || (isBank ? bankTitle : null)}
                    hint={
                        isBank
                            ? `Send ${formatPrice(pkg.package_total)} to the bank account above, then upload a screenshot or transaction ID below.${method.instructions ? ` ${method.instructions}` : ''}`
                            : `Send ${formatPrice(pkg.package_total)} to the number above, then upload a screenshot or SMS ID below.${method.instructions ? ` ${method.instructions}` : ''}`
                    }
                />
            )}

            <form onSubmit={submit} className="mt-4 space-y-4 border-t border-gray-100 pt-4">
                <DocumentUploadField
                    id={`proof-${pkg.seller_id}`}
                    label="Upload payment proof"
                    hint="Upload a screenshot of your MoMo or bank payment confirmation"
                    required={false}
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    maxSizeMb={5}
                    value={data.proof}
                    onChange={(file) => setData('proof', file)}
                    error={errors.proof}
                />
                <div>
                    <Label htmlFor={`ref-${pkg.seller_id}`}>Transaction ID (optional)</Label>
                    <Input
                        id={`ref-${pkg.seller_id}`}
                        placeholder="From MoMo or bank SMS — skip if you upload a screenshot"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                        className="mt-1"
                    />
                    {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                </div>
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full bg-green-600 py-6 text-base font-semibold text-white hover:bg-green-700"
                >
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    I&apos;ve paid — create order
                </Button>
            </form>
        </div>
    );
}
