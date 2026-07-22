import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

interface SellerOption {
    id: number;
    name: string;
    email?: string | null;
    mobile?: string | null;
}

interface CreateAnnouncementProps {
    sellers: SellerOption[];
}

type Audience = 'one' | 'selected' | 'all';

export default function CreateAnnouncement({ sellers }: CreateAnnouncementProps) {
    const { flash } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const form = useForm({
        audience: 'one' as Audience,
        title: '',
        body: '',
        seller_ids: [] as number[],
        send_email: false,
    });

    const selectedSet = useMemo(() => new Set(form.data.seller_ids), [form.data.seller_ids]);

    const filteredSellers = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return sellers;
        return sellers.filter((seller) =>
            [seller.name, seller.email, seller.mobile]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(q)),
        );
    }, [sellers, query]);

    const setAudience = (audience: Audience) => {
        form.setData({
            ...form.data,
            audience,
            seller_ids:
                audience === 'all'
                    ? []
                    : audience === 'one'
                      ? form.data.seller_ids.slice(0, 1)
                      : form.data.seller_ids,
        });
    };

    const toggleSeller = (id: number) => {
        if (form.data.audience === 'one') {
            form.setData('seller_ids', [id]);
            return;
        }

        const next = selectedSet.has(id)
            ? form.data.seller_ids.filter((x) => x !== id)
            : [...form.data.seller_ids, id];
        form.setData('seller_ids', next);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.announcements.store'));
    };

    return (
        <AdminLayout title="Message Sellers" active="announcements">
            <Head title="Send seller message" />

            {flash.error && (
                <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>
            )}

            <div className="mb-4">
                <Link href={route('admin.announcements.index')} className="text-sm text-orange-500 hover:underline">
                    ← Back to history
                </Link>
            </div>

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Send message to sellers</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Sellers see this in their notification bell. Optionally also email and SMS them.
                    </p>
                </div>

                <div>
                    <Label className="mb-2 block">Audience</Label>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {(
                            [
                                { value: 'one', label: 'One seller' },
                                { value: 'selected', label: 'Selected sellers' },
                                { value: 'all', label: 'All approved sellers' },
                            ] as const
                        ).map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => setAudience(option.value)}
                                className={`rounded-lg border px-3 py-3 text-left text-sm transition ${
                                    form.data.audience === option.value
                                        ? 'border-orange-500 bg-orange-50 font-medium text-orange-900'
                                        : 'border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>

                {form.data.audience !== 'all' && (
                    <div>
                        <Label className="mb-2 block">
                            {form.data.audience === 'one' ? 'Choose seller' : 'Choose sellers'}
                            {form.data.audience === 'selected' && form.data.seller_ids.length > 0
                                ? ` (${form.data.seller_ids.length} selected)`
                                : ''}
                        </Label>
                        <div className="relative mb-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search by name, email, or phone…"
                                className="pl-9"
                                aria-label="Search sellers"
                            />
                        </div>
                        <div className="max-h-64 overflow-y-auto rounded-lg border">
                            {sellers.length === 0 ? (
                                <p className="p-4 text-sm text-gray-500">No approved sellers yet.</p>
                            ) : filteredSellers.length === 0 ? (
                                <p className="p-4 text-sm text-gray-500">No sellers match “{query.trim()}”.</p>
                            ) : (
                                <ul className="divide-y">
                                    {filteredSellers.map((seller) => {
                                        const checked = selectedSet.has(seller.id);
                                        return (
                                            <li key={seller.id}>
                                                <label className="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-gray-50">
                                                    <input
                                                        type={form.data.audience === 'one' ? 'radio' : 'checkbox'}
                                                        name="seller"
                                                        checked={checked}
                                                        onChange={() => toggleSeller(seller.id)}
                                                        className="mt-1"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block text-sm font-medium text-gray-900">{seller.name}</span>
                                                        <span className="block text-xs text-gray-500">
                                                            {[seller.email, seller.mobile].filter(Boolean).join(' · ')}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                        <InputError message={form.errors.seller_ids} />
                    </div>
                )}

                <div>
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        className="mt-1"
                        placeholder="e.g. Important account update"
                        required
                        maxLength={150}
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div>
                    <Label htmlFor="body">Message</Label>
                    <textarea
                        id="body"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        className="mt-1 min-h-32 w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Write a clear message sellers should see in their notification bell…"
                        required
                        maxLength={5000}
                    />
                    <InputError message={form.errors.body} />
                </div>

                <label className="flex items-start gap-3 rounded-lg border p-3">
                    <input
                        type="checkbox"
                        checked={form.data.send_email}
                        onChange={(e) => form.setData('send_email', e.target.checked)}
                        className="mt-1"
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-900">Also send email & SMS</span>
                        <span className="text-xs text-gray-500">
                            In-app notification is always created. Turn this on for urgent notices.
                        </span>
                    </span>
                </label>

                <div className="flex flex-wrap gap-3">
                    <Button type="submit" disabled={form.processing} className="bg-orange-500 hover:bg-orange-600">
                        {form.processing ? 'Sending…' : 'Send message'}
                    </Button>
                    <Link href={route('admin.announcements.index')}>
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
