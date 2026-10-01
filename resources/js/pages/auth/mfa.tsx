import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import OtpCodeInput from '@/components/otp-code-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

type Props = {
    methods: string[];
    emailHint: string | null;
    portal: string;
};

export default function MfaChallenge({ methods, emailHint, portal }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [method, setMethod] = useState(methods.includes('email') ? 'email' : 'totp');
    const form = useForm({ method, code: '' });
    const resend = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, method }));
        form.post(route('mfa.verify'));
    };

    return (
        <ShopLayout>
            <Head title="Sign-in code" />
            <div className="mx-auto max-w-md px-4 py-12">
                <div className="rounded-2xl border border-orange-100 bg-white p-8 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wider text-orange-500">
                        {portal === 'admin' ? 'Admin' : portal === 'seller' ? 'Seller' : 'Shopper'}
                    </p>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Enter your sign-in code</h1>
                    <p className="mt-1 text-sm text-gray-500">Password accepted. Finish with the method you turned on.</p>

                    {flash?.success ? <p className="mt-3 text-sm text-emerald-700">{flash.success}</p> : null}

                    {methods.length > 1 ? (
                        <div className="mt-5 flex gap-2">
                            {methods.includes('email') ? (
                                <button
                                    type="button"
                                    className={`rounded-full px-3 py-1 text-sm font-semibold ${method === 'email' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'}`}
                                    onClick={() => setMethod('email')}
                                >
                                    Email
                                </button>
                            ) : null}
                            {methods.includes('totp') ? (
                                <button
                                    type="button"
                                    className={`rounded-full px-3 py-1 text-sm font-semibold ${method === 'totp' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'}`}
                                    onClick={() => setMethod('totp')}
                                >
                                    Authenticator
                                </button>
                            ) : null}
                        </div>
                    ) : null}

                    <form onSubmit={submit} className="mt-5 space-y-4">
                        <div>
                            <Label htmlFor="code">{method === 'email' ? 'Email code' : 'Authenticator code'}</Label>
                            <OtpCodeInput
                                id="code"
                                value={form.data.code}
                                onChange={(code) => form.setData('code', code)}
                                disabled={form.processing}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                {method === 'email'
                                    ? `Sent to ${emailHint ?? 'your email'}. Gmail and other inboxes both work.`
                                    : 'Open the authenticator app you scanned and enter the current code.'}
                            </p>
                            <InputError message={form.errors.code || form.errors.method} />
                        </div>
                        <Button type="submit" disabled={form.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            Continue
                        </Button>
                    </form>

                    {method === 'email' ? (
                        <button
                            type="button"
                            className="mt-3 text-sm font-semibold text-orange-600"
                            disabled={resend.processing}
                            onClick={() => resend.post(route('mfa.email'))}
                        >
                            Send a new email code
                        </button>
                    ) : null}
                </div>
            </div>
        </ShopLayout>
    );
}
