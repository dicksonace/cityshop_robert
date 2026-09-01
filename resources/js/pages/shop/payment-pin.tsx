import { Head, Link } from '@inertiajs/react';
import { Shield } from 'lucide-react';

import PaymentPinForm, { type PaymentPinFormProps } from '@/components/wallet/payment-pin-form';
import ShopLayout from '@/layouts/shop-layout';

export default function ShopPaymentPin(props: PaymentPinFormProps) {
    return (
        <ShopLayout>
            <Head title="Payment PIN" />
            <div className="mx-auto max-w-lg px-4 py-6 sm:py-8">
                <Link href={route('account.index')} className="text-sm font-semibold text-orange-600 hover:underline">
                    &larr; Back to account
                </Link>

                <div className="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <Shield className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Payment PIN</h1>
                            <p className="text-sm text-gray-500">Protects wallet checkout, withdrawals, and QR transfers</p>
                        </div>
                    </div>

                    <div className="mt-5">
                        <PaymentPinForm {...props} />
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
