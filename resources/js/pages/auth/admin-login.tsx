import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Shield } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface AdminLoginProps {
    canResetPassword: boolean;
    status?: string;
}

export default function AdminLogin({ canResetPassword, status }: AdminLoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
        portal: 'admin' as const,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Admin Login" />
            <div className="relative flex min-h-svh items-center justify-center overflow-hidden bg-slate-950 px-4 py-12">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-800/80 via-slate-950 to-slate-950" />
                <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-indigo-500/10 blur-3xl" />

                <div className="relative w-full max-w-md">
                    <div className="mb-8 text-center">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 shadow-lg shadow-black/20">
                            <Shield className="h-7 w-7 text-indigo-400" />
                        </div>
                        <h1 className="mt-5 text-2xl font-bold tracking-tight text-white">CityShop Control</h1>
                        <p className="mt-2 text-sm text-slate-400">Administrator access only</p>
                    </div>

                    <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-black/30 backdrop-blur">
                        <div className="mb-6 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-500">
                            <Lock className="h-3.5 w-3.5" />
                            Secure sign in
                        </div>

                        {status && <p className="mb-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-400">{status}</p>}

                        <form className="flex flex-col gap-4" onSubmit={submit}>
                            <div>
                                <Label htmlFor="login" className="text-slate-300">Email or mobile</Label>
                                <Input
                                    id="login"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    required
                                    autoFocus
                                    className="mt-1 border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus-visible:ring-indigo-500"
                                    placeholder="admin@cityshop.com"
                                />
                                <InputError message={errors.login} />
                            </div>
                            <div>
                                <Label htmlFor="password" className="text-slate-300">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    className="mt-1 border-slate-700 bg-slate-950 text-white focus-visible:ring-indigo-500"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-slate-400">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-600 bg-slate-950"
                                />
                                Remember this device
                            </label>
                            <Button type="submit" disabled={processing} className="w-full bg-indigo-600 hover:bg-indigo-500">
                                {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Sign in to dashboard
                            </Button>
                        </form>

                        {canResetPassword && (
                            <p className="mt-4 text-center text-sm">
                                <TextLink href={route('password.request')} className="text-indigo-400 hover:text-indigo-300">
                                    Forgot password?
                                </TextLink>
                            </p>
                        )}
                    </div>

                    <p className="mt-8 text-center text-xs text-slate-600">
                        Not an administrator?{' '}
                        <TextLink href={route('login')} className="text-slate-400 hover:text-slate-300">
                            Return to shop
                        </TextLink>
                    </p>
                </div>
            </div>
        </>
    );
}
