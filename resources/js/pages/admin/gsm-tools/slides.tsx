import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type Slide = {
    id: number;
    image_url: string;
    sort_order: number;
    active: boolean;
};

export default function PlaceOrderSlides({ slides }: { slides: Slide[] }) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ image: null as File | null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.gsm-tools.slides.store'), {
            forceFormData: true,
            onSuccess: () => form.reset('image'),
        });
    };

    return (
        <AdminLayout title="Place order slides" active="gsm-tools">
            <Head title="Place order slides" />
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">Place order slides</h1>
                        <p className="text-sm text-gray-500">These pictures slide above the wallet balance on Place order, on the website and the app.</p>
                    </div>
                    <button type="button" className="text-sm font-bold text-orange-600" onClick={() => router.visit(route('admin.gsm-tools.index'))}>
                        ← GSM orders
                    </button>
                </div>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="font-bold text-gray-900">Add a slide</h2>
                    <input
                        className="block w-full text-sm"
                        type="file"
                        accept="image/*"
                        required
                        onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)}
                    />
                    {form.errors.image ? <p className="text-sm text-red-600">{form.errors.image}</p> : null}
                    <button type="submit" disabled={form.processing || !form.data.image} className="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                        Add slide
                    </button>
                </form>

                <div className="space-y-3">
                    {slides.length === 0 ? <p className="text-sm text-gray-500">No slides yet. Add one and it will show on Place order.</p> : null}
                    {slides.map((slide, index) => (
                        <div key={slide.id} className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                            <img src={slide.image_url} alt="" className="aspect-[16/7] w-full bg-slate-950 object-contain" />
                            <div className="flex items-center justify-between gap-3 px-4 py-3">
                                <p className="text-sm text-gray-500">Slide {index + 1}{slide.active ? '' : ' · hidden'}</p>
                                <div className="flex gap-3">
                                    <button
                                        type="button"
                                        className="text-sm font-bold text-slate-700"
                                        onClick={() =>
                                            router.post(route('admin.gsm-tools.slides.active', slide.id), { active: slide.active ? 0 : 1 })
                                        }
                                    >
                                        {slide.active ? 'Hide' : 'Show'}
                                    </button>
                                    <button
                                        type="button"
                                        className="text-sm font-bold text-red-600"
                                        onClick={() => {
                                            if (confirm('Remove this slide?')) {
                                                router.delete(route('admin.gsm-tools.slides.destroy', slide.id));
                                            }
                                        }}
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
