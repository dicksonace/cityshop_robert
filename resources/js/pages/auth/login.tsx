import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';

interface LoginProps {
    canResetPassword: boolean;
    status?: string;
}

export default function Login({ canResetPassword, status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <ShopLayout>
            <Head title="Login" />
            <div className="mx-auto max-w-md px-4 py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h1 className="text-2xl font-bold text-gray-900">Welcome Back</h1>
                    <p className="mt-1 text-sm text-gray-500">Login with your mobile number or email.</p>

                    {status && <p className="mt-4 text-sm text-green-600">{status}</p>}

                    <form className="mt-6 flex flex-col gap-4" onSubmit={submit}>
                        <div>
                            <Label htmlFor="login">Mobile or Email</Label>
                            <Input
                                id="login"
                                value={data.login}
                                onChange={(e) => setData('login', e.target.value)}
                                required
                                autoFocus
                                className="mt-1"
                                placeholder="0241234567 or email@example.com"
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
                                className="mt-1"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                            Remember me
                        </label>
                        <Button type="submit" disabled={processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Login
                        </Button>
                    </form>

                    {canResetPassword && (
                        <p className="mt-4 text-center text-sm">
                            <TextLink href={route('password.request')}>Forgot password?</TextLink>
                        </p>
                    )}

                    <div className="mt-6 space-y-2 border-t border-gray-100 pt-4 text-center text-sm text-gray-500">
                        <p>
                            New buyer? <TextLink href={route('register.buyer')}>Create account</TextLink>
                        </p>
                        <p>
                            Want to sell on CityShop?{' '}
                            <TextLink href={route('contact')}>Contact support</TextLink>
                        </p>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
