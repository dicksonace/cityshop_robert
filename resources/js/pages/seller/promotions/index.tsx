import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { formatPrice, Paginated } from '@/types/marketplace';

interface Coupon {
    id: number;
    code: string;
    type: string;
    value: number;
    min_order_amount: number;
    max_uses: number | null;
    used_count: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
}

interface PromotionsProps {
    coupons: Paginated<Coupon>;
    types: { value: string; label: string }[];
}

export default function PromotionsIndex({ coupons, types }: PromotionsProps) {
    const form = useForm({
        code: '',
        type: 'percentage',
        value: '10',
        min_order_amount: '',
        max_uses: '',
        starts_at: '',
        ends_at: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seller.promotions.store'), { onSuccess: () => form.reset() });
    };

    return (
        <SellerLayout title="Promotions" active="promotions">
            <Head title="Promotions" />

            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={submit} className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-gray-900">Create coupon</h2>
                    <div className="mt-4 space-y-3">
                        <div>
                            <Label>Code</Label>
                            <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} required className="mt-1 font-mono uppercase" placeholder="SAVE10" />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Type</Label>
                                <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Value {form.data.type === 'percentage' ? '(%)' : '(GH₵)'}</Label>
                                <Input type="number" step="0.01" value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} required className="mt-1" />
                                <InputError message={form.errors.value} />
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Min order (GH₵)</Label>
                                <Input type="number" step="0.01" value={form.data.min_order_amount} onChange={(e) => form.setData('min_order_amount', e.target.value)} className="mt-1" />
                            </div>
                            <div>
                                <Label>Max uses</Label>
                                <Input type="number" value={form.data.max_uses} onChange={(e) => form.setData('max_uses', e.target.value)} className="mt-1" placeholder="Unlimited" />
                            </div>
                        </div>
                        <Button type="submit" disabled={form.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            <Plus className="mr-2 h-4 w-4" /> Create coupon
                        </Button>
                    </div>
                </form>

                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-gray-900">How it works</h2>
                    <ul className="mt-3 space-y-2 text-sm text-gray-600">
                        <li>· Buyers enter your code at checkout for that seller&apos;s items.</li>
                        <li>· Each buyer can use a coupon once.</li>
                        <li>· Discount applies to that seller&apos;s subtotal only.</li>
                    </ul>
                </div>
            </div>

            <div className="mt-8 rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div className="border-b px-6 py-4">
                    <h2 className="font-semibold">Your coupons</h2>
                </div>
                {coupons.data.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">No coupons yet.</p>
                ) : (
                    <div className="divide-y">
                        {coupons.data.map((c) => (
                            <div key={c.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                                <div>
                                    <p className="font-mono font-bold text-orange-600">{c.code}</p>
                                    <p className="text-sm text-gray-500 capitalize">
                                        {c.type.replace('_', ' ')} · {c.type === 'percentage' ? `${c.value}%` : formatPrice(c.value)}
                                        {c.min_order_amount > 0 && ` · Min ${formatPrice(c.min_order_amount)}`}
                                    </p>
                                    <p className="text-xs text-gray-400">Used {c.used_count}{c.max_uses ? ` / ${c.max_uses}` : ''}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${c.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'}`}>
                                        {c.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                    <Button variant="ghost" size="icon" className="text-red-500" onClick={() => router.delete(route('seller.promotions.destroy', c.id))}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SellerLayout>
    );
}
