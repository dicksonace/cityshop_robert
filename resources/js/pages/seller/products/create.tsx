import { Head, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

import ImageUploader from '@/components/seller/image-uploader';
import CategorySpecFields from '@/components/seller/category-spec-fields';
import ProductPreviewPanel, { ProductPreviewData } from '@/components/seller/product-preview-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { Category, SellerProfile } from '@/types/marketplace';

interface CreateProductProps {
    categories: Category[];
    profile?: SellerProfile | null;
}

const STEPS = ['Basics', 'Pricing', 'Shipping', 'Advanced & SEO', 'Media & publish'] as const;

export default function CreateProduct({ categories, profile }: CreateProductProps) {
    const [step, setStep] = useState(0);
    const [imagesConfirmed, setImagesConfirmed] = useState(false);
    const [imageFiles, setImageFiles] = useState<File[]>([]);
    const [imagePreviews, setImagePreviews] = useState<string[]>([]);
    const [previewMode, setPreviewMode] = useState<'grid' | 'detail'>('grid');
    const [shippingType, setShippingType] = useState<'free' | 'paid' | 'buyer'>('buyer');

    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        description: '',
        category_id: '',
        sku: '',
        brand: '',
        condition: 'new',
        price: '',
        discount_price: '',
        quantity: '1',
        low_stock_alert: '5',
        weight: '',
        free_shipping: false,
        delivery_fee: '',
        delivery_days: '',
        in_ghana: true,
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        wholesale_price: '',
        minimum_order_quantity: '1',
        is_negotiable: false,
        cash_on_delivery: false,
        pickup_available: false,
        ships_nationwide: true,
        specifications: {} as Record<string, string>,
        images: [] as File[],
    });

    transform((formData) => ({ ...formData, images: imageFiles }));

    useEffect(() => {
        const urls = imageFiles.map((f) => URL.createObjectURL(f));
        setImagePreviews(urls);
        return () => urls.forEach((u) => URL.revokeObjectURL(u));
    }, [imageFiles]);

    useEffect(() => {
        if (shippingType === 'free') {
            setData((d) => ({ ...d, free_shipping: true, delivery_fee: '' }));
        } else if (shippingType === 'paid') {
            setData((d) => ({ ...d, free_shipping: false }));
        } else {
            setData((d) => ({ ...d, free_shipping: false, delivery_fee: '' }));
        }
    }, [shippingType, setData]);

    const previewData: ProductPreviewData = useMemo(() => ({
        name: data.name,
        description: data.description,
        price: data.price,
        discount_price: data.discount_price,
        free_shipping: data.free_shipping,
        delivery_fee: data.delivery_fee,
        delivery_days: data.delivery_days,
        brand: data.brand,
        condition: data.condition,
        quantity: data.quantity,
        category_id: data.category_id,
        imagePreviews,
    }), [data, imagePreviews]);

    const canNext = () => {
        if (step === 0) return data.name.trim().length > 0;
        if (step === 1) return data.price !== '' && Number(data.price) >= 0;
        if (step === 2) return true;
        if (step === 3) return true;
        return imageFiles.length > 0 && imagesConfirmed;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (step < STEPS.length - 1) {
            if (canNext()) setStep((s) => s + 1);
            return;
        }
        if (!imagesConfirmed || imageFiles.length === 0) return;
        post(route('seller.products.store'), { forceFormData: true });
    };

    return (
        <SellerLayout title="Add Product" active="products">
            <Head title="Add Product" />

            <div className="w-full max-w-full overflow-x-hidden">
                {/* Mobile: compact step indicator (avoids wide horizontal scroll breaking layout) */}
                <div className="mb-4 lg:hidden">
                    <p className="text-sm font-medium text-gray-500">
                        Step {step + 1} of {STEPS.length}
                    </p>
                    <p className="text-lg font-semibold text-gray-900">{STEPS[step]}</p>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-gray-200">
                        <div
                            className="h-full rounded-full bg-orange-500 transition-all"
                            style={{ width: `${((step + 1) / STEPS.length) * 100}%` }}
                        />
                    </div>
                </div>

                {/* Desktop: full step trail */}
                <div className="mb-6 hidden items-center gap-2 overflow-x-auto pb-2 lg:flex">
                    {STEPS.map((label, i) => (
                        <div key={label} className="flex shrink-0 items-center gap-2">
                            <div className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${i <= step ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                {i + 1}
                            </div>
                            <span className={`text-sm font-medium ${i === step ? 'text-gray-900' : 'text-gray-400'}`}>{label}</span>
                            {i < STEPS.length - 1 && <ChevronRight className="h-4 w-4 text-gray-300" />}
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="flex w-full min-w-0 flex-col gap-6 lg:grid lg:grid-cols-2 lg:gap-8">
                    <div className="order-1 w-full min-w-0 space-y-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                    {step === 0 && (
                        <>
                            <div>
                                <Label>Product name *</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required className="mt-1" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>Category</Label>
                                    <select value={data.category_id} onChange={(e) => { setData('category_id', e.target.value); setData('specifications', {}); }} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                        <option value="">Select category</option>
                                        {categories.map((c) => <option key={c.id} value={c.id}>{c.icon ? `${c.icon} ` : ''}{c.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <Label>Brand</Label>
                                    <Input value={data.brand} onChange={(e) => setData('brand', e.target.value)} className="mt-1" />
                                </div>
                                <div>
                                    <Label>Condition</Label>
                                    <select value={data.condition} onChange={(e) => setData('condition', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                        <option value="new">New</option>
                                        <option value="used">Used</option>
                                        <option value="refurbished">Refurbished</option>
                                    </select>
                                </div>
                                <div>
                                    <Label>SKU</Label>
                                    <Input value={data.sku} onChange={(e) => setData('sku', e.target.value)} className="mt-1" />
                                </div>
                            </div>
                            <CategorySpecFields categoryId={data.category_id} categories={categories} specifications={data.specifications} onChange={(specs) => setData('specifications', specs)} errors={errors as Record<string, string>} />
                        </>
                    )}

                    {step === 1 && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Price (GH₵) *</Label>
                                <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} required className="mt-1" />
                                <InputError message={errors.price} />
                            </div>
                            <div>
                                <Label>Sale price</Label>
                                <Input type="number" step="0.01" value={data.discount_price} onChange={(e) => setData('discount_price', e.target.value)} className="mt-1" placeholder="Optional discount" />
                                <InputError message={errors.discount_price} />
                            </div>
                            <div>
                                <Label>Stock quantity *</Label>
                                <Input type="number" value={data.quantity} onChange={(e) => setData('quantity', e.target.value)} required className="mt-1" />
                            </div>
                            <div>
                                <Label>Low stock alert</Label>
                                <Input type="number" value={data.low_stock_alert} onChange={(e) => setData('low_stock_alert', e.target.value)} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <Label>Weight (kg)</Label>
                                <Input type="number" step="0.01" value={data.weight} onChange={(e) => setData('weight', e.target.value)} className="mt-1" />
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <Label>Delivery option</Label>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    { id: 'free' as const, title: 'Free delivery', desc: 'You cover shipping' },
                                    { id: 'paid' as const, title: 'Paid delivery', desc: 'Charge a delivery fee' },
                                    { id: 'buyer' as const, title: 'Arrange with buyer', desc: 'Discuss after order' },
                                ].map((opt) => (
                                    <button
                                        key={opt.id}
                                        type="button"
                                        onClick={() => setShippingType(opt.id)}
                                        className={`rounded-xl border p-4 text-left transition ${shippingType === opt.id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300'}`}
                                    >
                                        <p className="font-semibold text-gray-900">{opt.title}</p>
                                        <p className="mt-1 text-xs text-gray-500">{opt.desc}</p>
                                    </button>
                                ))}
                            </div>
                            {shippingType === 'paid' && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>Delivery fee (GH₵)</Label>
                                        <Input type="number" step="0.01" value={data.delivery_fee} onChange={(e) => setData('delivery_fee', e.target.value)} className="mt-1" />
                                    </div>
                                    <div>
                                        <Label>Estimated days</Label>
                                        <Input type="number" value={data.delivery_days} onChange={(e) => setData('delivery_days', e.target.value)} className="mt-1" placeholder="e.g. 3" />
                                    </div>
                                </div>
                            )}
                            {shippingType !== 'paid' && (
                                <div>
                                    <Label>Estimated delivery (days)</Label>
                                    <Input type="number" value={data.delivery_days} onChange={(e) => setData('delivery_days', e.target.value)} className="mt-1" placeholder="Optional" />
                                </div>
                            )}
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.in_ghana} onChange={(e) => setData('in_ghana', e.target.checked)} />
                                Product ships within Ghana
                            </label>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>Wholesale price (GH₵)</Label>
                                    <Input type="number" step="0.01" value={data.wholesale_price} onChange={(e) => setData('wholesale_price', e.target.value)} className="mt-1" />
                                </div>
                                <div>
                                    <Label>Minimum order qty</Label>
                                    <Input type="number" value={data.minimum_order_quantity} onChange={(e) => setData('minimum_order_quantity', e.target.value)} className="mt-1" />
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-4 text-sm">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.is_negotiable} onChange={(e) => setData('is_negotiable', e.target.checked)} /> Price negotiable</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.cash_on_delivery} onChange={(e) => setData('cash_on_delivery', e.target.checked)} /> Cash on delivery</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.pickup_available} onChange={(e) => setData('pickup_available', e.target.checked)} /> Pickup available</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.ships_nationwide} onChange={(e) => setData('ships_nationwide', e.target.checked)} /> Ships nationwide</label>
                            </div>
                            <div>
                                <Label>SEO title</Label>
                                <Input value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} className="mt-1" placeholder={data.name || 'Defaults to product name'} />
                            </div>
                            <div>
                                <Label>SEO description</Label>
                                <textarea value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm" rows={2} />
                            </div>
                            <div>
                                <Label>Search keywords</Label>
                                <Input value={data.meta_keywords} onChange={(e) => setData('meta_keywords', e.target.value)} className="mt-1" placeholder="phone, samsung, android" />
                            </div>
                        </div>
                    )}

                    {step === 4 && (
                        <>
                            <ImageUploader
                                maxImages={5}
                                onChange={(files) => { setImageFiles(files); setData('images', files); if (!files.length) setImagesConfirmed(false); }}
                                onConfirmedChange={setImagesConfirmed}
                                error={errors.images}
                            />
                            <div>
                                <Label>Description</Label>
                                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm" rows={6} placeholder="Describe your product, features, what's included..." />
                            </div>
                        </>
                    )}

                    <div className="flex gap-3 border-t pt-4">
                        {step > 0 && (
                            <Button type="button" variant="outline" onClick={() => setStep((s) => s - 1)}>
                                <ChevronLeft className="mr-1 h-4 w-4" /> Back
                            </Button>
                        )}
                        <Button type="submit" disabled={processing || !canNext()} className="ml-auto bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            {step < STEPS.length - 1 ? <>Next <ChevronRight className="ml-1 h-4 w-4" /></> : 'Publish product'}
                        </Button>
                    </div>
                </div>

                <div className="order-2 hidden w-full min-w-0 lg:block">
                    <ProductPreviewPanel
                        data={previewData}
                        categories={categories}
                        profile={profile}
                        previewMode={previewMode}
                        onPreviewModeChange={setPreviewMode}
                    />
                </div>
            </form>
            </div>
        </SellerLayout>
    );
}
