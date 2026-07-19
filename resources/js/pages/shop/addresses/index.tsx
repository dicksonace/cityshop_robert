import { Head, Link, router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Star, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { BuyerAddress } from '@/types/buyer-address';

interface IndexProps {
    addresses: BuyerAddress[];
}

export default function AddressesIndex({ addresses }: IndexProps) {
    return (
        <ShopLayout>
            <Head title="My addresses" />
            <div className="mx-auto max-w-2xl px-3 py-4 sm:px-4 sm:py-8">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-orange-50">
                            <MapPin className="h-5 w-5 text-orange-500" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">My addresses</h1>
                            <p className="text-sm text-gray-500">Save once — reuse on every order</p>
                        </div>
                    </div>
                    <Button asChild className="bg-orange-500 hover:bg-orange-600">
                        <Link href={route('addresses.create')}>
                            <Plus className="mr-1 h-4 w-4" />
                            Add
                        </Link>
                    </Button>
                </div>

                {addresses.length === 0 ? (
                    <div className="mt-8 rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
                        <MapPin className="mx-auto h-12 w-12 text-gray-200" />
                        <p className="mt-4 font-medium text-gray-900">No saved addresses yet</p>
                        <p className="mt-1 text-sm text-gray-500">Add a delivery address for faster checkout.</p>
                        <Button asChild className="mt-6 bg-orange-500 hover:bg-orange-600">
                            <Link href={route('addresses.create')}>Add address</Link>
                        </Button>
                    </div>
                ) : (
                    <ul className="mt-6 space-y-3">
                        {addresses.map((address) => (
                            <li key={address.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-gray-900">{address.full_name}</p>
                                            {address.is_default && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-700">
                                                    <Star className="h-3 w-3" /> Default
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600">{address.address_line}</p>
                                        {address.additional_details && (
                                            <p className="text-sm text-gray-500">{address.additional_details}</p>
                                        )}
                                        <p className="mt-1 text-sm text-gray-500">
                                            {address.city}, {address.region}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-500">{address.phone}</p>
                                        {address.secondary_phone && (
                                            <p className="text-xs text-gray-400">Alt: {address.secondary_phone}</p>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={route('addresses.edit', address.id)}>
                                            <Pencil className="mr-1 h-3.5 w-3.5" />
                                            Edit
                                        </Link>
                                    </Button>
                                    {! address.is_default && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.post(route('addresses.default', address.id))}
                                        >
                                            Set default
                                        </Button>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="border-red-200 text-red-600 hover:bg-red-50"
                                        onClick={() => {
                                            if (! window.confirm('Delete this address?')) return;
                                            router.delete(route('addresses.destroy', address.id));
                                        }}
                                    >
                                        <Trash2 className="mr-1 h-3.5 w-3.5" />
                                        Delete
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </ShopLayout>
    );
}
