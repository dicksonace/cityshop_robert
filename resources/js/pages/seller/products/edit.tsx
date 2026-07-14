import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import ImageUploader from '@/components/seller/image-uploader';
import ProductVideoUploader from '@/components/seller/product-video-uploader';
import CategorySpecFields from '@/components/seller/category-spec-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { Category, Product } from '@/types/marketplace';

interface EditProductProps {
    product: Product;
    categories: Category[];
}

export default function EditProduct({ product, categories }: EditProductProps) {
    const [imagesConfirmed, setImagesConfirmed] = useState(!!product.images?.length);
    const [imageFiles, setImageFiles] = useState<File[]>([]);
    const [removeIds, setRemoveIds] = useState<number[]>([]);
    const [videoFile, setVideoFile] = useState<File | null>(null);
    const [videoDuration, setVideoDuration] = useState<number | null>(null);
    const [removeVideo, setRemoveVideo] = useState(false);

    const initialFee = (product as Product & { delivery_fee?: number | null }).delivery_fee;
    const initialShipping: 'free' | 'paid' | 'buyer' = product.free_shipping
        ? 'free'
        : initialFee != null && Number(initialFee) > 0
            ? 'paid'
            : 'buyer';
    const [shippingType, setShippingType] = useState<'free' | 'paid' | 'buyer'>(initialShipping);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: product.name,
        description: product.description ?? '',
        category_id: product.category?.id?.toString() ?? '',
        sku: product.sku ?? '',
        brand: product.brand ?? '',
        price: product.price.toString(),
        discount_price: product.discount_price?.toString() ?? '',
        quantity: product.quantity.toString(),
        weight: '',
        free_shipping: product.free_shipping,
        delivery_fee: (product as Product & { delivery_fee?: number }).delivery_fee?.toString() ?? '',
        delivery_days: (product as Product & { delivery_days?: number }).delivery_days?.toString() ?? '',
        in_ghana: product.in_ghana,
        meta_title: (product as Product & { meta_title?: string }).meta_title ?? '',
        meta_description: (product as Product & { meta_description?: string }).meta_description ?? '',
        meta_keywords: (product as Product & { meta_keywords?: string }).meta_keywords ?? '',
        wholesale_price: (product as Product & { wholesale_price?: number }).wholesale_price?.toString() ?? '',
        minimum_order_quantity: String((product as Product & { minimum_order_quantity?: number }).minimum_order_quantity ?? 1),
        is_negotiable: (product as Product & { is_negotiable?: boolean }).is_negotiable ?? false,
        cash_on_delivery: (product as Product & { cash_on_delivery?: boolean }).cash_on_delivery ?? false,
        pickup_available: (product as Product & { pickup_available?: boolean }).pickup_available ?? false,
        ships_nationwide: (product as Product & { ships_nationwide?: boolean }).ships_nationwide ?? true,
        condition: (product as Product & { condition?: string }).condition ?? 'new',
        specifications: (product.specifications ?? {}) as Record<string, string>,
        _method: 'PUT',
    });

    transform((formData) => ({
        ...formData,
        images: imageFiles,
        image_count: imageFiles.length,
        remove_images: removeIds,
        video: videoFile,
        video_duration: videoDuration,
        remove_video: removeVideo,
    }));

    const totalImages = (product.images?.length ?? 0) - removeIds.length + imageFiles.length;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (totalImages === 0 || !imagesConfirmed) return;
        post(route('seller.products.update', product.id), { forceFormData: true });
    };

    return (
        <SellerLayout title="Edit Product" active="products">
            <Head title="Edit Product" />
            <form onSubmit={submit} className="max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
                <ImageUploader
                    maxImages={5}
                    existingImages={product.images ?? []}
                    onChange={(files, removed) => {
                        setImageFiles(files);
                        setRemoveIds(removed);
                    }}
                    onConfirmedChange={setImagesConfirmed}
                    error={errors.images}
                />

                <ProductVideoUploader
                    existingPath={product.video_path}
                    existingDuration={product.video_duration}
                    removeExisting={removeVideo}
                    onChange={(file, duration) => {
                        setVideoFile(file);
                        setVideoDuration(duration);
                        if (file) setRemoveVideo(false);
                    }}
                    onRemoveExisting={() => {
                        setRemoveVideo(true);
                        setVideoFile(null);
                        setVideoDuration(null);
                    }}
                    error={errors.video ?? errors.video_duration}
                />

                <div>
                    <Label>Product Name</Label>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required className="mt-1" />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label>Description</Label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm"
                        rows={4}
                    />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>Category</Label>
                        <select
                            value={data.category_id}
                            onChange={(e) => {
                                setData('category_id', e.target.value);
                                setData('specifications', {});
                            }}
                            className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm"
                        >
                            <option value="">Select category</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.icon ? `${c.icon} ` : ''}{c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label>Price (GH₵)</Label>
                        <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} required className="mt-1" />
                    </div>
                    <div>
                        <Label>Discount Price</Label>
                        <Input type="number" step="0.01" value={data.discount_price} onChange={(e) => setData('discount_price', e.target.value)} className="mt-1" />
                    </div>
                    <div>
                        <Label>Quantity</Label>
                        <Input type="number" value={data.quantity} onChange={(e) => setData('quantity', e.target.value)} required className="mt-1" />
                    </div>
                </div>

                <CategorySpecFields
                    categoryId={data.category_id}
                    categories={categories}
                    specifications={data.specifications}
                    onChange={(specs) => setData('specifications', specs)}
                    errors={errors as Record<string, string>}
                />

                <div className="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Delivery</h3>
                        <p className="text-xs text-gray-500">Buyers see this on the product page.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            { id: 'free' as const, title: 'Free delivery', desc: 'You cover shipping' },
                            { id: 'paid' as const, title: 'Paid delivery', desc: 'Charge a delivery fee' },
                            { id: 'buyer' as const, title: 'Arrange with buyer', desc: 'Discuss after order' },
                        ].map((opt) => (
                            <button
                                key={opt.id}
                                type="button"
                                onClick={() => {
                                    setShippingType(opt.id);
                                    if (opt.id === 'free') {
                                        setData((d) => ({ ...d, free_shipping: true, delivery_fee: '' }));
                                    } else if (opt.id === 'paid') {
                                        setData((d) => ({ ...d, free_shipping: false }));
                                    } else {
                                        setData((d) => ({ ...d, free_shipping: false, delivery_fee: '' }));
                                    }
                                }}
                                className={`rounded-xl border bg-white p-3 text-left transition ${
                                    shippingType === opt.id ? 'border-orange-500 ring-1 ring-orange-200' : 'border-gray-200'
                                }`}
                            >
                                <p className="text-sm font-semibold text-gray-900">{opt.title}</p>
                                <p className="mt-1 text-xs text-gray-500">{opt.desc}</p>
                            </button>
                        ))}
                    </div>
                    {shippingType === 'paid' && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Delivery fee (GH₵)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.delivery_fee}
                                    onChange={(e) => setData('delivery_fee', e.target.value)}
                                    className="mt-1 bg-white"
                                    required
                                />
                                <InputError message={errors.delivery_fee} />
                            </div>
                            <div>
                                <Label>Estimated days</Label>
                                <Input
                                    type="number"
                                    value={data.delivery_days}
                                    onChange={(e) => setData('delivery_days', e.target.value)}
                                    className="mt-1 bg-white"
                                    placeholder="e.g. 3"
                                />
                            </div>
                        </div>
                    )}
                    {shippingType !== 'paid' && (
                        <div>
                            <Label>Estimated delivery (days)</Label>
                            <Input
                                type="number"
                                value={data.delivery_days}
                                onChange={(e) => setData('delivery_days', e.target.value)}
                                className="mt-1 bg-white"
                                placeholder="Optional"
                            />
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 space-y-3">
                    <h3 className="font-semibold text-gray-900">Advanced & SEO</h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div><Label>SEO title</Label><Input value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} className="mt-1" /></div>
                        <div><Label>Keywords</Label><Input value={data.meta_keywords} onChange={(e) => setData('meta_keywords', e.target.value)} className="mt-1" /></div>
                    </div>
                    <div><Label>SEO description</Label><textarea value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm" rows={2} /></div>
                    <div className="flex flex-wrap gap-3 text-sm">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.cash_on_delivery} onChange={(e) => setData('cash_on_delivery', e.target.checked)} /> COD</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.pickup_available} onChange={(e) => setData('pickup_available', e.target.checked)} /> Pickup</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.is_negotiable} onChange={(e) => setData('is_negotiable', e.target.checked)} /> Negotiable</label>
                    </div>
                </div>

                <Button
                    type="submit"
                    disabled={processing || !imagesConfirmed || totalImages === 0}
                    className="w-full bg-orange-500 hover:bg-orange-600 disabled:opacity-50"
                >
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Update Product
                </Button>
            </form>
        </SellerLayout>
    );
}
