import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';

import KycVerificationForm, { type KycPayload } from '@/components/wallet/kyc-verification-form';
import SellerLayout from '@/layouts/seller-layout';
import { SharedData } from '@/types';

type Props = {
    kyc: KycPayload;
};

export default function SellerKycPage({ kyc }: Props) {
    const { flash } = usePage<SharedData>().props;

    return (
        <SellerLayout title="Ghana Card" active="account">
            <Head title="Ghana Card verification" />

            <div className="mx-auto max-w-lg space-y-4">
                <Link href={route('seller.account')} className="text-sm font-semibold text-orange-600 hover:underline">
                    &larr; Back to account
                </Link>

                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <BadgeCheck className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Ghana Card verification</h1>
                            <p className="text-sm text-gray-500">Required before wallet payouts and RMB</p>
                        </div>
                    </div>

                    {flash?.success && (
                        <p className="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</p>
                    )}

                    <div className="mt-5">
                        <KycVerificationForm kyc={kyc} />
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
