import { useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GhanaLocationFields from '@/components/shop/ghana-location-fields';

interface Props {
    updateUrl: string;
    name: string;
    email: string;
    mobile?: string | null;
    ghanaCardNumber?: string | null;
    region?: string | null;
    city?: string | null;
    residentialAddress?: string | null;
    storeName: string;
    isBusinessRegistered?: boolean;
    businessName?: string | null;
    businessRegistrationNumber?: string | null;
    acceptMarketplacePayments?: boolean;
    acceptDirectPayments?: boolean;
}

export default function SellerInformationForm({
    updateUrl,
    name,
    email,
    mobile,
    ghanaCardNumber,
    region,
    city,
    residentialAddress,
    storeName,
    isBusinessRegistered = false,
    businessName,
    businessRegistrationNumber,
    acceptMarketplacePayments = true,
    acceptDirectPayments = false,
}: Props) {
    const form = useForm({
        name: name ?? '',
        email: email ?? '',
        mobile: mobile ?? '',
        ghana_card_number: ghanaCardNumber ?? '',
        region: region ?? '',
        city: city ?? '',
        residential_address: residentialAddress ?? '',
        store_name: storeName ?? '',
        is_business_registered: isBusinessRegistered,
        business_name: businessName ?? '',
        business_registration_number: businessRegistrationNumber ?? '',
        accept_marketplace_payments: acceptMarketplacePayments,
        accept_direct_payments: acceptDirectPayments,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(updateUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
            <div className="rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Personal Information</h3>
                <p className="mt-1 text-sm text-gray-500">Admin can update seller identity and address.</p>
                <div className="mt-4 space-y-4">
                    <Field label="Name" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                    </Field>
                    <Field label="Phone number" error={form.errors.mobile}>
                        <Input type="tel" value={form.data.mobile} onChange={(e) => form.setData('mobile', e.target.value)} required />
                    </Field>
                    <Field label="Ghana Card" error={form.errors.ghana_card_number}>
                        <Input
                            value={form.data.ghana_card_number}
                            onChange={(e) => form.setData('ghana_card_number', e.target.value)}
                            placeholder="GHA-XXXXXXXXX-X"
                        />
                    </Field>
                    <GhanaLocationFields
                        region={form.data.region}
                        city={form.data.city}
                        onRegionChange={(value) => form.setData('region', value)}
                        onCityChange={(value) => form.setData('city', value)}
                        regionError={form.errors.region}
                        cityError={form.errors.city}
                    />
                    <Field label="Address" error={form.errors.residential_address}>
                        <Input
                            value={form.data.residential_address}
                            onChange={(e) => form.setData('residential_address', e.target.value)}
                        />
                    </Field>
                </div>
            </div>

            <div className="rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Business Information</h3>
                <p className="mt-1 text-sm text-gray-500">Store name and how buyers can pay this seller.</p>
                <div className="mt-4 space-y-4">
                    <label className="flex items-center gap-2 text-sm font-medium text-gray-800">
                        <input
                            type="checkbox"
                            className="h-4 w-4 accent-orange-500"
                            checked={form.data.is_business_registered}
                            onChange={(e) => form.setData('is_business_registered', e.target.checked)}
                        />
                        Business is registered
                    </label>
                    <Field label="Store name" error={form.errors.store_name}>
                        <Input value={form.data.store_name} onChange={(e) => form.setData('store_name', e.target.value)} required />
                    </Field>
                    {form.data.is_business_registered && (
                        <>
                            <Field label="Registered business name" error={form.errors.business_name}>
                                <Input
                                    value={form.data.business_name}
                                    onChange={(e) => form.setData('business_name', e.target.value)}
                                />
                            </Field>
                            <Field label="Registration number" error={form.errors.business_registration_number}>
                                <Input
                                    value={form.data.business_registration_number}
                                    onChange={(e) => form.setData('business_registration_number', e.target.value)}
                                />
                            </Field>
                        </>
                    )}
                    <div>
                        <p className="text-sm font-medium text-gray-800">Buyer payment modes</p>
                        <div className="mt-2 space-y-2">
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 accent-orange-500"
                                    checked={form.data.accept_marketplace_payments}
                                    onChange={(e) => form.setData('accept_marketplace_payments', e.target.checked)}
                                />
                                Marketplace
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 accent-orange-500"
                                    checked={form.data.accept_direct_payments}
                                    onChange={(e) => form.setData('accept_direct_payments', e.target.checked)}
                                />
                                Direct to seller
                            </label>
                        </div>
                        <InputError message={form.errors.accept_marketplace_payments} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Save seller information
                    </Button>
                </div>
            </div>
        </form>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
