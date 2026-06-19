import { Head, Link } from '@inertiajs/react';
import { Ban } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';

interface SellerBlockedProps {
    reason?: string | null;
}

export default function SellerBlocked({ reason }: SellerBlockedProps) {
    return (
        <ShopLayout>
            <Head title="Account Suspended" />
            <div className="mx-auto max-w-lg px-4 py-16 text-center">
                <div className="rounded-2xl border border-red-100 bg-white p-8 shadow-sm">
                    <Ban className="mx-auto h-16 w-16 text-red-500" />
                    <h1 className="mt-4 text-2xl font-bold text-gray-900">Seller Account Blocked</h1>
                    <p className="mt-2 text-gray-500">
                        Your seller account has been suspended by CityShop. Your products are hidden from buyers and you cannot access the seller dashboard.
                    </p>
                    {reason && (
                        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-left text-sm text-red-700">
                            <p className="font-medium">Reason</p>
                            <p className="mt-1">{reason}</p>
                        </div>
                    )}
                    <Link href={route('contact')} className="mt-6 inline-block text-orange-500 hover:underline">
                        Contact support
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
