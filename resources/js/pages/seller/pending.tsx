import { Head } from '@inertiajs/react';
import { Clock } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';

export default function SellerPending() {
    return (
        <ShopLayout>
            <Head title="Application Pending" />
            <div className="mx-auto max-w-lg px-4 py-16 text-center">
                <div className="rounded-2xl bg-white p-8 shadow-sm">
                    <Clock className="mx-auto h-16 w-16 text-orange-500" />
                    <h1 className="mt-4 text-2xl font-bold text-gray-900">Application Under Review</h1>
                    <p className="mt-2 text-gray-500">
                        Thank you for applying to sell on CityShop. Our team is reviewing your documents. You will be notified once approved.
                    </p>
                </div>
            </div>
        </ShopLayout>
    );
}
