import { Link } from '@inertiajs/react';

import SeoHead from '@/components/seo-head';
import ShopLayout from '@/layouts/shop-layout';

interface ContactInfo {
    email?: string;
    phone?: string;
    whatsapp?: string;
}

interface Props {
    contact: ContactInfo;
    updatedAt: string;
}

export default function Privacy({ contact, updatedAt }: Props) {
    const email = contact.email || 'support@cityunlock.net';

    return (
        <ShopLayout>
            <SeoHead
                title="Privacy Policy"
                description="How CityShop collects, uses, and protects your personal data."
                url="/privacy"
            />
            <div className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-3xl font-bold text-gray-900">Privacy Policy</h1>
                <p className="mt-2 text-sm text-gray-500">Last updated: {updatedAt}</p>
                <p className="mt-4 text-gray-600">
                    CityShop (operated via cityunlock.net) respects your privacy. This policy explains what we collect
                    and how we use it when you use our website and mobile apps.
                </p>

                <div className="prose prose-gray mt-8 max-w-none space-y-6 text-sm leading-relaxed text-gray-700">
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">1. Information we collect</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            <li>Account details: name, mobile number, email, country, region, and city</li>
                            <li>Delivery addresses and order history</li>
                            <li>Wallet and payment-related information (for example MoMo references and bank details you submit for funding or withdrawals)</li>
                            <li>KYC documents you upload (such as Ghana Card) for wallet and compliance features</li>
                            <li>Photos, videos, and messages you upload for products, chat, livestream, or support</li>
                            <li>Device and app information for security and push notifications (for example device tokens)</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">2. How we use your information</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            <li>Create and manage your buyer or seller account</li>
                            <li>Process orders, deliveries, refunds, and disputes</li>
                            <li>Operate wallet top-ups, withdrawals, and marketplace payments</li>
                            <li>Verify identity where required (KYC)</li>
                            <li>Send service messages (order updates, security alerts, support replies)</li>
                            <li>Improve safety, prevent fraud, and maintain the platform</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">3. Sharing</h2>
                        <p className="mt-2">
                            We share information only as needed to run CityShop: with sellers for your orders, with
                            payment providers (such as Paystack or Flutterwave) to process payments, with SMS/push
                            providers for notifications, and with authorities if required by law. We do not sell your
                            personal data.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">4. Security</h2>
                        <p className="mt-2">
                            We use HTTPS encryption in transit and restrict access to personal data. No method of
                            transmission or storage is 100% secure, so please protect your password and payment PIN.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">5. Data retention and deletion</h2>
                        <p className="mt-2">
                            We keep account and transaction records as needed for operations, fraud prevention, and
                            legal requirements. You may request account deletion or data access by contacting support.
                            Some records may be retained where required by law or to resolve disputes.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">6. Your choices</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            <li>Update profile and address details in the app or website</li>
                            <li>Contact support to request account deletion</li>
                            <li>Disable push notifications in device settings</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">7. Children</h2>
                        <p className="mt-2">
                            CityShop is not directed at children under 13. If you believe a child provided personal
                            data, contact us and we will take appropriate action.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-gray-900">8. Contact</h2>
                        <p className="mt-2">
                            Questions about privacy:{' '}
                            <a className="font-medium text-orange-600 hover:underline" href={`mailto:${email}`}>
                                {email}
                            </a>
                            . You can also use our{' '}
                            <Link href={route('contact')} className="font-medium text-orange-600 hover:underline">
                                Contact page
                            </Link>
                            .
                        </p>
                    </section>
                </div>
            </div>
        </ShopLayout>
    );
}
