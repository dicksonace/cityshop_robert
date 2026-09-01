import { Head, Link } from '@inertiajs/react';
import { Shield } from 'lucide-react';

import PaymentPinForm, { type PaymentPinFormProps } from '@/components/wallet/payment-pin-form';
import SellerLayout from '@/layouts/seller-layout';

export default function SellerPaymentPin(props: PaymentPinFormProps) {
    return (
        <SellerLayout title="Payment PIN" active="account">
            <Head title="Payment PIN" />

            <div className="mx-auto max-w-lg space-y-4">
                <Link href={route('seller.account')} className="text-sm font-semibold text-orange-600 hover:underline">
                    &larr; Back to account
                </Link>

                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <Shield className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Payment PIN</h1>
                            <p className="text-sm text-gray-500">Protects wallet withdrawals and QR transfers</p>
                        </div>
                    </div>

                    <div className="mt-5">
                        <PaymentPinForm {...props} />
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
