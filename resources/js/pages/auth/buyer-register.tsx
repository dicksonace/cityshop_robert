import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

export default function BuyerRegister() {
    const { errors: pageErrors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        mobile: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const formErrors = { ...(pageErrors ?? {}), ...errors };
    const hasErrors = Object.keys(formErrors).length > 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register.buyer'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <ShopLayout>
            <Head title="Create Buyer Account" />
            <div className="mx-auto max-w-md px-4 py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h1 className="text-2xl font-bold text-gray-900">Create Account</h1>
                    <p className="mt-1 text-sm text-gray-500">Join CityShop and start shopping today.</p>

                    {hasErrors && (
                        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            Please fix the highlighted fields and try again.
                        </div>
                    )}

                    <form className="mt-6 flex flex-col gap-4" onSubmit={submit}>
                        <div>
                            <Label htmlFor="name">Full Name</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required className="mt-1" />
                            <InputError message={formErrors.name} />
                        </div>
                        <div>
                            <Label htmlFor="mobile">Mobile Number</Label>
                            <Input id="mobile" value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} required className="mt-1" placeholder="0241234567" />
                            <InputError message={formErrors.mobile} />
                        </div>
                        <div>
                            <Label htmlFor="email">Email Address</Label>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required className="mt-1" />
                            <InputError message={formErrors.email} />
                        </div>
                        <div>
                            <Label htmlFor="password">Password</Label>
                            <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required className="mt-1" />
                            <InputError message={formErrors.password} />
                        </div>
                        <div>
                            <Label htmlFor="password_confirmation">Confirm Password</Label>
                            <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required className="mt-1" />
                            <InputError message={formErrors.password_confirmation} />
                        </div>
                        <Button type="submit" disabled={processing} className="mt-2 w-full bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Create Account
                        </Button>
                    </form>

                    <p className="mt-4 text-center text-sm text-gray-500">
                        Want to sell on CityShop?{' '}
                        <TextLink href={route('contact')}>Contact support</TextLink>
                    </p>
                    <p className="mt-2 text-center text-sm text-gray-500">
                        Already have an account? <TextLink href={route('login')}>Log in</TextLink>
                    </p>
                </div>
            </div>
        </ShopLayout>
    );
}
