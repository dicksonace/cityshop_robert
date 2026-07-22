import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

interface BuyerOption {
    id: number;
    name: string;
    email?: string | null;
    mobile?: string | null;
}

interface CreateBuyerAnnouncementProps {
    buyers: BuyerOption[];
}

type Audience = 'one' | 'selected' | 'all';

export default function CreateBuyerAnnouncement({ buyers }: CreateBuyerAnnouncementProps) {
    const { flash } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const form = useForm({
        audience: 'one' as Audience,
        title: '',
        body: '',
        buyer_ids: [] as number[],
        send_email: false,
    });

    const selectedSet = useMemo(() => new Set(form.data.buyer_ids), [form.data.buyer_ids]);

    const filteredBuyers = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return buyers;
        return buyers.filter((buyer) =>
            [buyer.name, buyer.email, buyer.mobile]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(q)),
        );
    }, [buyers, query]);

    const setAudience = (audience: Audience) => {
        form.setData({
            ...form.data,
            audience,
            buyer_ids:
                audience === 'all'
                    ? []
                    : audience === 'one'
                      ? form.data.buyer_ids.slice(0, 1)
                      : form.data.buyer_ids,
        });
    };

    const toggleBuyer = (id: number) => {
        if (form.data.audience === 'one') {
            form.setData('buyer_ids', [id]);
            return;
        }

        const next = selectedSet.has(id)
            ? form.data.buyer_ids.filter((x) => x !== id)
            : [...form.data.buyer_ids, id];
        form.setData('buyer_ids', next);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.buyer-announcements.store'));
    };

    return (
        <AdminLayout title="Message Buyers" active="buyer-announcements">
            <Head title="Send buyer message" />

            {flash.error && (
                <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>
            )}

            <div className="mb-4">
                <Link href={route('admin.buyer-announcements.index')} className="text-sm text-orange-500 hover:underline">
                    ← Back to history
                </Link>
            </div>

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Send message to buyers</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Buyers see this in their notification bell. Optionally also email and SMS them.
                    </p>
                </div>

                <div>
                    <Label className="mb-2 block">Audience</Label>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {(
                            [
                                { value: 'one', label: 'One buyer' },
                                { value: 'selected', label: 'Selected buyers' },
                                { value: 'all', label: 'All buyers' },
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
                            {form.data.audience === 'one' ? 'Choose buyer' : 'Choose buyers'}
                            {form.data.audience === 'selected' && form.data.buyer_ids.length > 0
                                ? ` (${form.data.buyer_ids.length} selected)`
                                : ''}
                        </Label>
                        <div className="relative mb-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search by name, email, or phone…"
                                className="pl-9"
                                aria-label="Search buyers"
                            />
                        </div>
                        <div className="max-h-64 overflow-y-auto rounded-lg border">
                            {buyers.length === 0 ? (
                                <p className="p-4 text-sm text-gray-500">No buyers yet.</p>
                            ) : filteredBuyers.length === 0 ? (
                                <p className="p-4 text-sm text-gray-500">No buyers match “{query.trim()}”.</p>
                            ) : (
                                <ul className="divide-y">
                                    {filteredBuyers.map((buyer) => {
                                        const checked = selectedSet.has(buyer.id);
                                        return (
                                            <li key={buyer.id}>
                                                <label className="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-gray-50">
                                                    <input
                                                        type={form.data.audience === 'one' ? 'radio' : 'checkbox'}
                                                        name="buyer"
                                                        checked={checked}
                                                        onChange={() => toggleBuyer(buyer.id)}
                                                        className="mt-1"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block text-sm font-medium text-gray-900">{buyer.name}</span>
                                                        <span className="block text-xs text-gray-500">
                                                            {[buyer.email, buyer.mobile].filter(Boolean).join(' · ')}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                        <InputError message={form.errors.buyer_ids} />
                    </div>
                )}

                <div>
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        className="mt-1"
                        placeholder="e.g. Order delivery update"
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
                        placeholder="Write a clear message buyers should see in their notification bell…"
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
                    <Link href={route('admin.buyer-announcements.index')}>
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
