import { Head, useForm, usePage } from '@inertiajs/react';
import { BarChart3, LoaderCircle, Package, Store, Truck, Wallet } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

import LoginErrorBanner from '@/components/auth/login-error-banner';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/contexts/toast-context';
import { SharedData } from '@/types';

interface SellerLoginProps {
    canResetPassword: boolean;
    status?: string;
    defaultLogin?: string;
}

const highlights = [
    { icon: Package, text: 'List and manage your products' },
    { icon: Truck, text: 'Process orders and shipments' },
    { icon: Wallet, text: 'Track earnings and withdrawals' },
    { icon: BarChart3, text: 'View store analytics' },
];

export default function SellerLogin({ canResetPassword, status, defaultLogin = '' }: SellerLoginProps) {
    const { flash, errors: pageErrors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const { error: toastError } = useToast();
    const { data, setData, post, processing, errors: formErrors, reset } = useForm({
        login: defaultLogin,
        password: '',
        remember: false,
        portal: 'seller' as const,
    });

    const errors = { ...(pageErrors ?? {}), ...formErrors };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
            onError: (errs) => {
                const first = Object.values(errs)[0];
                if (typeof first === 'string') {
                    toastError(first);
                }
            },
        });
    };

    useEffect(() => {
        if (flash?.error) {
            toastError(flash.error);
        }
    }, [flash?.error, toastError]);

    return (
        <>
            <Head title="Seller Login" />
            <div className="grid min-h-svh lg:grid-cols-2">
                <div className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-800 to-teal-900 p-10 text-white lg:flex">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.12),transparent_45%)]" />
                    <div className="relative z-10">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                                <Store className="h-6 w-6" />
                            </div>
                            <div>
                                <p className="text-lg font-bold">CityShop</p>
                                <p className="text-sm text-emerald-100">Seller Centre</p>
                            </div>
                        </div>
                    </div>
                    <div className="relative z-10 space-y-6">
                        <div>
                            <h2 className="text-3xl font-bold leading-tight">Grow your business on CityShop</h2>
                            <p className="mt-3 max-w-md text-emerald-100">
                                Sign in to manage your store, fulfill orders, and reach buyers across Ghana.
                            </p>
                        </div>
                        <ul className="space-y-4">
                            {highlights.map(({ icon: Icon, text }) => (
                                <li key={text} className="flex items-center gap-3 text-sm text-emerald-50">
                                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
                                        <Icon className="h-4 w-4" />
                                    </span>
                                    {text}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <p className="relative z-10 text-xs text-emerald-200/80">Seller accounts are invite-only. Need access? Contact CityShop support.</p>
                </div>

                <div className="flex items-center justify-center bg-gradient-to-b from-emerald-50/50 to-white p-6 sm:p-10">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white">
                                <Store className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-bold text-gray-900">Seller Centre</p>
                                <p className="text-xs text-gray-500">CityShop for sellers</p>
                            </div>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900">Seller sign in</h1>
                        <p className="mt-1 text-sm text-gray-500">Use your seller mobile number or email.</p>

                        {status && <p className="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{status}</p>}

                        <div className="mt-4">
                            <LoginErrorBanner flashError={flash?.error} errors={errors} />
                        </div>

                        <form className="mt-8 flex flex-col gap-4" onSubmit={submit}>
                            <div>
                                <Label htmlFor="login">Mobile or email</Label>
                                <Input
                                    id="login"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    required
                                    autoFocus
                                    className="mt-1 border-emerald-100 focus-visible:ring-emerald-500"
                                    placeholder="0241234567 or seller@email.com"
                                />
                                <InputError message={errors.login} />
                            </div>
                            <div>
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    className="mt-1 border-emerald-100 focus-visible:ring-emerald-500"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                                Keep me signed in
                            </label>
                            <Button type="submit" disabled={processing} className="w-full bg-emerald-600 hover:bg-emerald-700">
                                {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Enter Seller Centre
                            </Button>
                        </form>

                        {canResetPassword && (
                            <p className="mt-4 text-center text-sm">
                                <TextLink href={route('password.request')}>Forgot password?</TextLink>
                            </p>
                        )}

                        <div className="mt-8 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
                            <p>
                                Shopping as a buyer?{' '}
                                <TextLink href={route('login')}>Shopper login</TextLink>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
