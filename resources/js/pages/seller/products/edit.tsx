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
import { Category, Product } from '@/types/marketplace';

interface EditProductProps {
    product: Product;
    categories: Category[];
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index'), active: true },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

export default function EditProduct({ product, categories }: EditProductProps) {
    const [imagesConfirmed, setImagesConfirmed] = useState(!!product.images?.length);
    const [imageFiles, setImageFiles] = useState<File[]>([]);
    const [removeIds, setRemoveIds] = useState<number[]>([]);

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
        in_ghana: product.in_ghana,
        specifications: (product.specifications ?? {}) as Record<string, string>,
        _method: 'PUT',
    });

    transform((formData) => ({
        ...formData,
        images: imageFiles,
        remove_images: removeIds,
    }));

    const totalImages = (product.images?.length ?? 0) - removeIds.length + imageFiles.length;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (totalImages === 0 || !imagesConfirmed) return;
        post(route('seller.products.update', product.id), { forceFormData: true });
    };

    return (
        <PanelLayout title="Edit Product" nav={nav}>
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

                <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.free_shipping} onChange={(e) => setData('free_shipping', e.target.checked)} />
                        Free Delivery
                    </label>
                </div>
                <p className="text-xs text-gray-500">You arrange delivery to the buyer. Free delivery means you deliver at no extra charge.</p>

                <Button
                    type="submit"
                    disabled={processing || !imagesConfirmed || totalImages === 0}
                    className="w-full bg-orange-500 hover:bg-orange-600 disabled:opacity-50"
                >
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Update Product
                </Button>
            </form>
        </PanelLayout>
    );
}
