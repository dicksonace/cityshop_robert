import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/contexts/toast-context';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

const fieldLabels: Record<string, string> = {
    first_name: 'First name',
    last_name: 'Last name',
    mobile: 'Mobile number',
    whatsapp: 'WhatsApp number',
    email: 'Email address',
    ghana_card_number: 'Ghana Card number',
    digital_address: 'Digital address',
    residential_address: 'Residential address',
    region: 'Region',
    city: 'City',
    password: 'Password',
    password_confirmation: 'Password confirmation',
    store_name: 'Store name',
    business_name: 'Business name',
    business_registration_number: 'Business registration number',
    business_address: 'Business address',
    id_card_front: 'Ghana Card (front)',
    id_card_back: 'Ghana Card (back)',
    shop_photo: 'Shop photo',
    form_a: 'Form A',
    form_b: 'Form B',
    business_certificate: 'Business certificate',
};

interface SellerDefaults {
    first_name: string;
    last_name: string;
    mobile: string;
    whatsapp: string;
    email: string;
    ghana_card_number: string;
    digital_address: string;
    residential_address: string;
    region: string;
    city: string;
}

interface SellerRegisterProps {
    token: string;
    expiresAt: string;
    isExistingUser?: boolean;
    defaults?: SellerDefaults;
}

export default function SellerRegister({ token, expiresAt, isExistingUser = false, defaults }: SellerRegisterProps) {
    const { flash } = usePage<SharedData>().props;
    const { error: toastError } = useToast();
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: defaults?.first_name ?? '',
        last_name: defaults?.last_name ?? '',
        mobile: defaults?.mobile ?? '',
        whatsapp: defaults?.whatsapp ?? '',
        email: defaults?.email ?? '',
        ghana_card_number: defaults?.ghana_card_number ?? '',
        digital_address: defaults?.digital_address ?? '',
        residential_address: defaults?.residential_address ?? '',
        region: defaults?.region ?? '',
        city: defaults?.city ?? '',
        password: '',
        password_confirmation: '',
        is_business_registered: false as boolean,
        business_name: '',
        business_registration_number: '',
        business_address: '',
        tin: '',
        store_name: '',
        id_card_front: null as File | null,
        id_card_back: null as File | null,
        shop_photo: null as File | null,
        form_a: null as File | null,
        form_b: null as File | null,
        business_certificate: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const requiredFiles: (keyof typeof data)[] = ['id_card_front', 'id_card_back', 'shop_photo'];
        if (data.is_business_registered) {
            requiredFiles.push('form_a', 'form_b', 'business_certificate');
        }

        const missingFiles = requiredFiles.filter((field) => !data[field]);
        if (missingFiles.length > 0) {
            const names = missingFiles.map((f) => fieldLabels[f] ?? f).join(', ');
            toastError(`Please upload: ${names}`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        post(route('register.seller', { token }), {
            forceFormData: true,
            onFinish: () => reset('password', 'password_confirmation'),
            onError: (errs) => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                const first = Object.values(errs)[0];
                if (typeof first === 'string') {
                    toastError(first);
                } else {
                    toastError('Please complete all required fields and try again.');
                }
            },
        });
    };

    useEffect(() => {
        if (flash.error) {
            toastError(flash.error);
        }
    }, [flash.error, toastError]);

    useEffect(() => {
        if (Object.keys(errors).length > 0) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [errors]);

    const fileInput = (field: keyof typeof data, label: string, required = true) => (
        <div>
            <Label>{label}</Label>
            <Input
                type="file"
                accept="image/*,.pdf"
                className="mt-1"
                onChange={(e) => setData(field, e.target.files?.[0] ?? null)}
                required={required}
            />
            <InputError message={errors[field]} />
        </div>
    );

    return (
        <ShopLayout>
            <Head title="Become a Seller" />
            <div className="mx-auto max-w-2xl px-4 py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h1 className="text-2xl font-bold text-gray-900">Seller Application</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        {isExistingUser
                            ? 'Complete your seller application using your existing account. This link expires in 24 hours and can only be used once.'
                            : 'Complete your application using your private invitation link. Admin will review within 24–48 hours.'}
                    </p>
                    <p className="mt-2 text-xs text-amber-700">
                        Link expires: {new Date(expiresAt).toLocaleString()}.
                    </p>

                    {flash.error && (
                        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {flash.error}
                        </div>
                    )}

                    {Object.keys(errors).length > 0 && (
                        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p className="font-medium">Please fix the following and submit again:</p>
                            <ul className="mt-2 list-inside list-disc space-y-1">
                                {Object.entries(errors).map(([field, message]) => (
                                    <li key={field}>
                                        <span className="font-medium">{fieldLabels[field] ?? field}:</span> {message}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <p className="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800">
                        All fields are required, including document uploads at the bottom of the form. After a successful
                        submit you will be taken to a &quot;Waiting for review&quot; page.
                    </p>

                    <form className="mt-6 space-y-8" onSubmit={submit}>
                        <section>
                            <h2 className="text-lg font-semibold text-gray-900">Personal Information</h2>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>First Name</Label>
                                    <Input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div>
                                    <Label>Last Name</Label>
                                    <Input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.last_name} />
                                </div>
                                <div>
                                    <Label>Mobile Number</Label>
                                    <Input value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.mobile} />
                                </div>
                                <div>
                                    <Label>WhatsApp Number</Label>
                                    <Input value={data.whatsapp} onChange={(e) => setData('whatsapp', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.whatsapp} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label>Email Address</Label>
                                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.email} />
                                </div>
                                <div>
                                    <Label>Ghana Card Number</Label>
                                    <Input value={data.ghana_card_number} onChange={(e) => setData('ghana_card_number', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.ghana_card_number} />
                                </div>
                                <div>
                                    <Label>Digital Address</Label>
                                    <Input value={data.digital_address} onChange={(e) => setData('digital_address', e.target.value)} required className="mt-1" placeholder="GA-XXX-XXXX" />
                                    <InputError message={errors.digital_address} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label>Residential Address</Label>
                                    <Input value={data.residential_address} onChange={(e) => setData('residential_address', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.residential_address} />
                                </div>
                                <div>
                                    <Label>Region</Label>
                                    <Input value={data.region} onChange={(e) => setData('region', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.region} />
                                </div>
                                <div>
                                    <Label>City</Label>
                                    <Input value={data.city} onChange={(e) => setData('city', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.city} />
                                </div>
                                {!isExistingUser && (
                                    <>
                                        <div>
                                            <Label>Password</Label>
                                            <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required className="mt-1" />
                                            <InputError message={errors.password} />
                                        </div>
                                        <div>
                                            <Label>Confirm Password</Label>
                                            <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required className="mt-1" />
                                            <InputError message={errors.password_confirmation} />
                                        </div>
                                    </>
                                )}
                            </div>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-gray-900">Identity Verification</h2>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                {fileInput('id_card_front', 'Ghana Card (Front)')}
                                {fileInput('id_card_back', 'Ghana Card (Back)')}
                            </div>
                        </section>

                        <section>
                            <h2 className="text-lg font-semibold text-gray-900">Business Information</h2>
                            <div className="mt-4">
                                <Label>Is your business registered?</Label>
                                <div className="mt-2 flex gap-4">
                                    <label className="flex items-center gap-2">
                                        <input type="radio" checked={data.is_business_registered} onChange={() => setData('is_business_registered', true)} />
                                        Yes
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input type="radio" checked={!data.is_business_registered} onChange={() => setData('is_business_registered', false)} />
                                        No
                                    </label>
                                </div>
                            </div>

                            {data.is_business_registered ? (
                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <Label>Business Name</Label>
                                        <Input value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} required className="mt-1" />
                                        <InputError message={errors.business_name} />
                                    </div>
                                    <div>
                                        <Label>Registration Number</Label>
                                        <Input value={data.business_registration_number} onChange={(e) => setData('business_registration_number', e.target.value)} required className="mt-1" />
                                        <InputError message={errors.business_registration_number} />
                                    </div>
                                    <div>
                                        <Label>TIN (optional)</Label>
                                        <Input value={data.tin} onChange={(e) => setData('tin', e.target.value)} className="mt-1" />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <Label>Business Address</Label>
                                        <Input value={data.business_address} onChange={(e) => setData('business_address', e.target.value)} required className="mt-1" />
                                        <InputError message={errors.business_address} />
                                    </div>
                                    {fileInput('form_a', 'Form A')}
                                    {fileInput('form_b', 'Form B')}
                                    {fileInput('business_certificate', 'Business Certificate')}
                                </div>
                            ) : (
                                <div className="mt-4">
                                    <Label>Store Name</Label>
                                    <Input value={data.store_name} onChange={(e) => setData('store_name', e.target.value)} required className="mt-1" />
                                    <InputError message={errors.store_name} />
                                </div>
                            )}

                            <div className="mt-4">{fileInput('shop_photo', 'Front of your Shop')}</div>
                        </section>

                        <Button type="submit" disabled={processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Submit Application
                        </Button>
                    </form>

                    {!isExistingUser && (
                        <p className="mt-4 text-center text-sm text-gray-500">
                            Want to buy instead? <TextLink href={route('register.buyer')}>Register as Buyer</TextLink>
                        </p>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
