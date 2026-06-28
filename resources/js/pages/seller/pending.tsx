import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, Mail, ShieldCheck, Store } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

interface SellerPendingProps {
    applicant: {
        name: string;
        email: string;
    };
    submittedAt?: string | null;
    justSubmitted?: boolean;
}

const steps = [
    {
        title: 'Application received',
        description: 'Your documents and store details are in our queue.',
        done: true,
    },
    {
        title: 'Admin review',
        description: 'CityShop verifies your identity and business information (24–48 hours).',
        active: true,
    },
    {
        title: 'Approval email',
        description: 'We will notify you at your registered email when approved.',
    },
    {
        title: 'Set up your store',
        description: 'After approval, complete store setup and start listing products.',
    },
];

export default function SellerPending({ applicant, submittedAt, justSubmitted = false }: SellerPendingProps) {
    const { flash } = usePage<SharedData>().props;
    const showSuccess = justSubmitted || Boolean(flash.success);

    return (
        <ShopLayout>
            <Head title="Application Submitted" />
            <div className="mx-auto max-w-xl px-4 py-10 sm:py-16">
                {showSuccess && (
                    <div className="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-emerald-900">
                        <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" />
                        <div>
                            <p className="font-semibold">Application submitted successfully</p>
                            <p className="mt-1 text-sm text-emerald-800">
                                {flash.success ??
                                    'Thank you. Your seller application is now waiting for admin review and verification.'}
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100">
                    <div className="text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-100">
                            <Clock className="h-8 w-8 text-orange-500" />
                        </div>
                        <h1 className="mt-4 text-2xl font-bold text-gray-900">Waiting for review</h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Hi {applicant.name.split(' ')[0]}, your seller application is under review. You cannot access
                            Seller Centre until an admin approves your account.
                        </p>
                    </div>

                    <div className="mt-6 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
                        <p>
                            <span className="font-medium text-gray-800">Applicant:</span> {applicant.name}
                        </p>
                        <p className="mt-1 flex items-center gap-1.5">
                            <Mail className="h-4 w-4 text-gray-400" />
                            {applicant.email}
                        </p>
                        {submittedAt && (
                            <p className="mt-1 text-xs text-gray-500">
                                Submitted {new Date(submittedAt).toLocaleString()}
                            </p>
                        )}
                    </div>

                    <ol className="mt-8 space-y-4">
                        {steps.map((step, index) => (
                            <li key={step.title} className="flex gap-3">
                                <div
                                    className={`mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                        step.done
                                            ? 'bg-emerald-500 text-white'
                                            : step.active
                                              ? 'bg-orange-500 text-white'
                                              : 'bg-gray-200 text-gray-500'
                                    }`}
                                >
                                    {step.done ? '✓' : index + 1}
                                </div>
                                <div>
                                    <p className="font-medium text-gray-900">{step.title}</p>
                                    <p className="text-sm text-gray-500">{step.description}</p>
                                </div>
                            </li>
                        ))}
                    </ol>

                    <div className="mt-8 space-y-3">
                        <Link
                            href={route('seller.login')}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            <Store className="h-4 w-4" />
                            Go to Seller Centre login
                        </Link>
                        <p className="text-center text-xs text-gray-500">
                            You can sign in anytime to check status. Dashboard access unlocks after approval.
                        </p>
                        <Link
                            href={route('contact')}
                            className="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-orange-500"
                        >
                            <ShieldCheck className="h-4 w-4" />
                            Questions? Contact support
                        </Link>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
