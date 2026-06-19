import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import ImageUploader from '@/components/seller/image-uploader';
import CategorySpecFields from '@/components/seller/category-spec-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PanelLayout from '@/layouts/panel-layout';
import { Category } from '@/types/marketplace';

interface CreateProductProps {
    categories: Category[];
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index'), active: true },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

export default function CreateProduct({ categories }: CreateProductProps) {
    const [imagesConfirmed, setImagesConfirmed] = useState(false);
    const [imageFiles, setImageFiles] = useState<File[]>([]);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        description: '',
        category_id: '',
        sku: '',
        brand: '',
        price: '',
        discount_price: '',
        quantity: '1',
        weight: '',
        free_shipping: false,
        in_ghana: true,
        specifications: {} as Record<string, string>,
        images: [] as File[],
    });

    transform((formData) => ({
        ...formData,
        images: imageFiles,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (imageFiles.length === 0 || !imagesConfirmed) return;
        post(route('seller.products.store'), { forceFormData: true });
    };

    return (
        <PanelLayout title="Add Product" nav={nav}>
            <Head title="Add Product" />
            <form onSubmit={submit} className="max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
                <ImageUploader
                    maxImages={5}
                    onChange={(files) => {
                        setImageFiles(files);
                        setData('images', files);
                        if (files.length === 0) setImagesConfirmed(false);
                    }}
                    onConfirmedChange={setImagesConfirmed}
                    error={errors.images}
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
                        <Label>Brand</Label>
                        <Input value={data.brand} onChange={(e) => setData('brand', e.target.value)} className="mt-1" />
                    </div>
                    <div>
                        <Label>Price (GH₵)</Label>
                        <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} required className="mt-1" />
                        <InputError message={errors.price} />
                    </div>
                    <div>
                        <Label>Discount Price</Label>
                        <Input type="number" step="0.01" value={data.discount_price} onChange={(e) => setData('discount_price', e.target.value)} className="mt-1" />
                    </div>
                    <div>
                        <Label>Quantity</Label>
                        <Input type="number" value={data.quantity} onChange={(e) => setData('quantity', e.target.value)} required className="mt-1" />
                    </div>
                    <div>
                        <Label>SKU</Label>
                        <Input value={data.sku} onChange={(e) => setData('sku', e.target.value)} className="mt-1" />
                    </div>
                </div>

                <CategorySpecFields
                    categoryId={data.category_id}
                    categories={categories}
                    specifications={data.specifications}
                    onChange={(specs) => setData('specifications', specs)}
                    errors={errors as Record<string, string>}
                />

                <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.free_shipping} onChange={(e) => setData('free_shipping', e.target.checked)} />
                        Free Delivery
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.in_ghana} onChange={(e) => setData('in_ghana', e.target.checked)} />
                        In Ghana
                    </label>
                </div>
                <p className="text-xs text-gray-500">You arrange delivery to the buyer. Free delivery means you deliver at no extra charge.</p>

                <Button
                    type="submit"
                    disabled={processing || !imagesConfirmed || imageFiles.length === 0}
                    className="w-full bg-orange-500 py-6 hover:bg-orange-600 disabled:opacity-50"
                >
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Submit for Review
                </Button>
                {!imagesConfirmed && imageFiles.length > 0 && (
                    <p className="text-center text-xs text-amber-600">Please confirm your images before submitting.</p>
                )}
            </form>
        </PanelLayout>
    );
}
