import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Pencil, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

interface AdminCategory {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
}

interface CategoriesIndexProps {
    categories: AdminCategory[];
}

export default function CategoriesIndex({ categories }: CategoriesIndexProps) {
    const { flash } = usePage<SharedData>().props;
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        icon: '',
        sort_order: '0',
    });

    const editForm = useForm({
        name: '',
        icon: '',
        is_active: true,
        sort_order: '0',
    });

    const startEdit = (category: AdminCategory) => {
        setEditingId(category.id);
        editForm.setData({
            name: category.name,
            icon: category.icon ?? '',
            is_active: category.is_active,
            sort_order: String(category.sort_order),
        });
    };

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('admin.categories.store'), {
            onSuccess: () => createForm.reset(),
        });
    };

    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingId) return;

        editForm.put(route('admin.categories.update', editingId), {
            onSuccess: () => setEditingId(null),
        });
    };

    const removeCategory = (category: AdminCategory) => {
        const message = category.products_count > 0
            ? `"${category.name}" has ${category.products_count} product(s). It will be hidden, not deleted. Continue?`
            : `Delete "${category.name}"?`;

        if (!window.confirm(message)) return;

        router.delete(route('admin.categories.destroy', category.id));
    };

    return (
        <AdminLayout title="Categories" active="categories">
            <Head title="Categories" />

            {flash.success && (
                <div className="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-xl bg-white p-6 shadow-sm lg:col-span-1">
                    <h2 className="font-semibold text-gray-900">Add category</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        New categories appear in the seller product form immediately.
                    </p>
                    <form className="mt-4 space-y-4" onSubmit={submitCreate}>
                        <div>
                            <Label>Name *</Label>
                            <Input
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder="Vehicles"
                                required
                                className="mt-1"
                            />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                            <Label>Icon (emoji)</Label>
                            <Input
                                value={createForm.data.icon}
                                onChange={(e) => createForm.setData('icon', e.target.value)}
                                placeholder="🚗"
                                className="mt-1"
                            />
                            <InputError message={createForm.errors.icon} />
                        </div>
                        <div>
                            <Label>Sort order</Label>
                            <Input
                                type="number"
                                value={createForm.data.sort_order}
                                onChange={(e) => createForm.setData('sort_order', e.target.value)}
                                className="mt-1"
                            />
                            <p className="mt-1 text-xs text-gray-400">Lower numbers appear first in dropdowns.</p>
                        </div>
                        <Button type="submit" disabled={createForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {createForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Add category
                        </Button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm lg:col-span-2">
                    <div className="border-b px-6 py-4">
                        <h2 className="font-semibold text-gray-900">All categories</h2>
                        <p className="text-sm text-gray-500">{categories.length} total</p>
                    </div>

                    {editingId !== null && (
                        <form onSubmit={submitEdit} className="border-b bg-orange-50 px-6 py-4">
                            <p className="mb-3 text-sm font-medium text-orange-900">Edit category</p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label>Name</Label>
                                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} className="mt-1 bg-white" required />
                                </div>
                                <div>
                                    <Label>Icon</Label>
                                    <Input value={editForm.data.icon} onChange={(e) => editForm.setData('icon', e.target.value)} className="mt-1 bg-white" />
                                </div>
                                <div>
                                    <Label>Sort order</Label>
                                    <Input type="number" value={editForm.data.sort_order} onChange={(e) => editForm.setData('sort_order', e.target.value)} className="mt-1 bg-white" />
                                </div>
                                <div className="flex items-end">
                                    <label className="flex items-center gap-2 pb-2 text-sm">
                                        <input type="checkbox" checked={editForm.data.is_active} onChange={(e) => editForm.setData('is_active', e.target.checked)} />
                                        Active (visible to sellers)
                                    </label>
                                </div>
                            </div>
                            <div className="mt-3 flex gap-2">
                                <Button type="submit" size="sm" disabled={editForm.processing}>Save</Button>
                                <Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>Cancel</Button>
                            </div>
                        </form>
                    )}

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-6 py-3">Category</th>
                                    <th className="px-6 py-3">Slug</th>
                                    <th className="px-6 py-3">Products</th>
                                    <th className="px-6 py-3">Order</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {categories.map((category) => (
                                    <tr key={category.id} className={!category.is_active ? 'bg-gray-50 text-gray-500' : undefined}>
                                        <td className="px-6 py-4 font-medium text-gray-900">
                                            <span className="mr-2">{category.icon ?? '📦'}</span>
                                            {category.name}
                                        </td>
                                        <td className="px-6 py-4 text-gray-500">{category.slug}</td>
                                        <td className="px-6 py-4">{category.products_count}</td>
                                        <td className="px-6 py-4">{category.sort_order}</td>
                                        <td className="px-6 py-4">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${category.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'}`}>
                                                {category.is_active ? 'Active' : 'Hidden'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => startEdit(category)}>
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" className="text-red-600 hover:text-red-700" onClick={() => removeCategory(category)}>
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
