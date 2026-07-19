import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, MapPin } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import GhanaLocationFields from '@/components/shop/ghana-location-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { BuyerAddress } from '@/types/buyer-address';
import { citiesForRegion, GHANA_REGIONS } from '@/lib/ghana-locations';

type AddressDefaults = {
    first_name?: string | null;
    last_name?: string | null;
    phone?: string | null;
    secondary_phone?: string | null;
    address_line?: string | null;
    additional_details?: string | null;
    region?: string | null;
    city?: string | null;
    digital_address?: string | null;
    is_default?: boolean;
};

interface FormProps {
    address: BuyerAddress | null;
    defaults?: AddressDefaults | null;
    returnTo?: string | null;
}

export default function AddressForm({ address, defaults, returnTo }: FormProps) {
    const seed = address ?? defaults ?? {};
    const regionSeed = GHANA_REGIONS.includes(seed.region ?? '') ? (seed.region as string) : '';
    const citySeed =
        regionSeed && citiesForRegion(regionSeed).includes(seed.city ?? '')
            ? (seed.city as string)
            : seed.city && regionSeed
              ? (seed.city as string)
              : '';

    const { data, setData, post, put, processing, errors } = useForm({
        first_name: seed.first_name ?? '',
        last_name: seed.last_name ?? '',
        phone: seed.phone ?? '',
        secondary_phone: seed.secondary_phone ?? '',
        address_line: seed.address_line ?? '',
        additional_details: seed.additional_details ?? '',
        region: regionSeed,
        city: citySeed,
        digital_address: seed.digital_address ?? '',
        is_default: address?.is_default ?? defaults?.is_default ?? true,
        return: returnTo ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (address) {
            put(route('addresses.update', address.id));
        } else {
            post(route('addresses.store'));
        }
    };

    return (
        <ShopLayout>
            <Head title={address ? 'Edit address' : 'Add address'} />
            <div className="mx-auto max-w-lg px-3 py-4 sm:px-4 sm:py-8">
                <Link
                    href={returnTo === 'checkout' ? route('checkout.index') : route('addresses.index')}
                    className="text-sm text-orange-500 hover:underline"
                >
                    ← Back
                </Link>
                <div className="mt-3 flex items-center gap-2">
                    <MapPin className="h-5 w-5 text-orange-500" />
                    <h1 className="text-2xl font-bold text-gray-900">{address ? 'Edit address' : 'Add address'}</h1>
                </div>
                <p className="mt-1 text-sm text-gray-500">Saved for your next orders — edit anytime.</p>

                <form onSubmit={submit} className="mt-6 space-y-6">
                    <div className="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="font-semibold text-gray-900">Contact details</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    className="mt-1"
                                    placeholder="Enter your first name"
                                    value={data.first_name}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.first_name} />
                            </div>
                            <div>
                                <Label htmlFor="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    className="mt-1"
                                    placeholder="Enter your last name"
                                    value={data.last_name}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.last_name} />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="phone">Mobile number</Label>
                            <div className="mt-1 flex overflow-hidden rounded-md border border-input bg-white">
                                <span className="flex items-center border-r bg-gray-50 px-3 text-sm text-gray-600">🇬🇭 +233</span>
                                <Input
                                    id="phone"
                                    className="border-0 focus-visible:ring-0 focus-visible:ring-offset-0"
                                    placeholder="24 000 0000"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    required
                                />
                            </div>
                            <p className="mt-1 text-xs text-gray-500">Use a number you can reach for delivery.</p>
                            <InputError message={errors.phone} />
                        </div>
                        <div>
                            <Label htmlFor="secondary_phone">Secondary mobile (optional)</Label>
                            <div className="mt-1 flex overflow-hidden rounded-md border border-input bg-white">
                                <span className="flex items-center border-r bg-gray-50 px-3 text-sm text-gray-600">🇬🇭 +233</span>
                                <Input
                                    id="secondary_phone"
                                    className="border-0 focus-visible:ring-0 focus-visible:ring-offset-0"
                                    placeholder="Optional"
                                    value={data.secondary_phone}
                                    onChange={(e) => setData('secondary_phone', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.secondary_phone} />
                        </div>
                    </div>

                    <div className="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="font-semibold text-gray-900">Address details</h2>
                        <div>
                            <Label htmlFor="address_line">Full address</Label>
                            <Input
                                id="address_line"
                                className="mt-1"
                                placeholder="e.g. Near the station, House No. 12"
                                value={data.address_line}
                                onChange={(e) => setData('address_line', e.target.value)}
                                required
                            />
                            <InputError message={errors.address_line} />
                        </div>
                        <div>
                            <Label htmlFor="additional_details">Additional details (optional)</Label>
                            <Input
                                id="additional_details"
                                className="mt-1"
                                placeholder="Landmark, floor, gate color…"
                                value={data.additional_details}
                                onChange={(e) => setData('additional_details', e.target.value)}
                            />
                            <InputError message={errors.additional_details} />
                        </div>
                        <GhanaLocationFields
                            region={data.region}
                            city={data.city}
                            onRegionChange={(region) => setData('region', region)}
                            onCityChange={(city) => setData('city', city)}
                            regionError={errors.region}
                            cityError={errors.city}
                        />
                        <div>
                            <Label htmlFor="digital_address">Digital address (optional)</Label>
                            <Input
                                id="digital_address"
                                className="mt-1"
                                placeholder="Ghana Post GPS"
                                value={data.digital_address}
                                onChange={(e) => setData('digital_address', e.target.value)}
                            />
                            <InputError message={errors.digital_address} />
                        </div>
                    </div>

                    <label className="flex cursor-pointer items-center justify-between gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <div>
                            <p className="font-medium text-gray-900">Default address</p>
                            <p className="text-sm text-gray-500">Set this as your primary delivery address</p>
                        </div>
                        <input
                            type="checkbox"
                            className="h-5 w-5 rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                            checked={data.is_default}
                            onChange={(e) => setData('is_default', e.target.checked)}
                        />
                    </label>

                    <Button type="submit" disabled={processing} className="w-full bg-orange-500 py-6 hover:bg-orange-600">
                        {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        {address ? 'Save changes' : 'Save address'}
                    </Button>

                    {address && (
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full border-red-200 text-red-600 hover:bg-red-50"
                            onClick={() => {
                                if (! window.confirm('Delete this address?')) return;
                                router.delete(route('addresses.destroy', address.id), {
                                    onSuccess: () => {
                                        if (returnTo === 'checkout') {
                                            router.visit(route('checkout.index'));
                                        }
                                    },
                                });
                            }}
                        >
                            Delete address
                        </Button>
                    )}
                </form>
            </div>
        </ShopLayout>
    );
}
