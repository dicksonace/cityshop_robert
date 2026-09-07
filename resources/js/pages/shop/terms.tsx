import { Link } from '@inertiajs/react';

import SeoHead from '@/components/seo-head';
import ShopLayout from '@/layouts/shop-layout';

interface ContactInfo {
    email?: string;
}

interface Props {
    contact: ContactInfo;
    updatedAt: string;
}

export default function Terms({ contact, updatedAt }: Props) {
    const email = contact.email || 'support@cityunlock.net';

    return (
        <ShopLayout>
            <SeoHead
                title="Terms of Service"
                description="Terms for using CityShop marketplace, wallet, and related services."
                url="/terms"
            />
            <div className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-3xl font-bold text-gray-900">Terms of Service</h1>
                <p className="mt-2 text-sm text-gray-500">Last updated: {updatedAt}</p>
                <p className="mt-4 text-gray-600">
                    By using CityShop (website and apps), you agree to these terms.
                </p>

                <div className="mt-8 space-y-6 text-sm leading-relaxed text-gray-700">
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">1. The service</h2>
                        <p className="mt-2">
                            CityShop is an online marketplace connecting buyers and sellers. We provide tools for
                            browsing, ordering, messaging, wallet funding, and related features. Sellers are
                            responsible for their products and fulfilment unless CityShop states otherwise.
                        </p>
                    </section>
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">2. Accounts</h2>
                        <p className="mt-2">
                            You must provide accurate information and keep your login, payment PIN, and device secure.
                            You are responsible for activity under your account. We may suspend accounts for fraud,
                            abuse, or policy violations.
                        </p>
                    </section>
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">3. Orders and payments</h2>
                        <p className="mt-2">
                            Prices are shown in Ghana Cedis unless stated otherwise. Payment methods may include wallet,
                            mobile money, card processors, and manual funding subject to verification. Refunds and
                            cancellations follow CityShop and seller policies for each order.
                        </p>
                    </section>
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">4. Wallet and transfers</h2>
                        <p className="mt-2">
                            Wallet balances and China/RMB transfer features (where available) are subject to limits,
                            verification, processing windows, and admin review. Incorrect recipient details may cause
                            delays or failed transfers.
                        </p>
                    </section>
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">5. Acceptable use</h2>
                        <p className="mt-2">
                            Do not use CityShop for illegal goods, fraud, harassment, spam, or attempts to bypass
                            security. We may remove content and restrict access to protect users and the platform.
                        </p>
                    </section>
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">6. Contact</h2>
                        <p className="mt-2">
                            Support:{' '}
                            <a className="font-medium text-orange-600 hover:underline" href={`mailto:${email}`}>
                                {email}
                            </a>{' '}
                            or{' '}
                            <Link href={route('contact')} className="font-medium text-orange-600 hover:underline">
                                Contact Us
                            </Link>
                            .
                        </p>
                    </section>
                </div>
            </div>
        </ShopLayout>
    );
}
