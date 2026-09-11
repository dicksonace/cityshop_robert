import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type FieldRow = {
    id?: number;
    label: string;
    placeholder: string;
    type: string;
    required: boolean;
    active?: boolean;
};

type Service = {
    id: number;
    name: string;
    description: string | null;
    price_ghs: number;
    sort_order: number;
    active: boolean;
    fields: Array<{
        id: number;
        label: string;
        placeholder: string | null;
        type: string;
        required: boolean;
        active: boolean;
    }>;
};

interface Props {
    services: Service[];
    fieldTypes: string[];
}

export default function AdminGsmServices({ services, fieldTypes }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        description: '',
        price_ghs: '50',
        sort_order: '0',
        active: true,
        fields: [{ label: '', placeholder: '', type: 'text', required: true }] as FieldRow[],
    });

    const editForm = useForm({
        name: '',
        description: '',
        price_ghs: '',
        sort_order: '0',
        active: true,
        fields: [] as FieldRow[],
    });

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('admin.gsm-tools.services.store'), {
            onSuccess: () => createForm.reset('name', 'description'),
        });
    };

    const startEdit = (service: Service) => {
        setEditingId(service.id);
        editForm.setData({
            name: service.name,
            description: service.description ?? '',
            price_ghs: String(service.price_ghs),
            sort_order: String(service.sort_order),
            active: service.active,
            fields: service.fields.map((f) => ({
                id: f.id,
                label: f.label,
                placeholder: f.placeholder ?? '',
                type: f.type,
                required: f.required,
                active: f.active,
            })),
        });
    };

    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingId) return;
        editForm.post(route('admin.gsm-tools.services.update', editingId), {
            onSuccess: () => setEditingId(null),
        });
    };

    return (
        <AdminLayout title="GSM Services" active="gsm-tools-services">
            <Head title="GSM Services" />
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">GSM Services</h1>
                        <p className="text-sm text-gray-500">Create services with custom input fields (IMEI, photo link, etc.).</p>
                    </div>
                    <button type="button" className="text-sm text-orange-600" onClick={() => router.visit(route('admin.gsm-tools.index'))}>
                        ← Orders
                    </button>
                </div>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <form onSubmit={submitCreate} className="space-y-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="font-bold text-gray-900">New service</h2>
                    <div>
                        <Label>Name</Label>
                        <Input className="mt-1" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                        <InputError message={createForm.errors.name} />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={3}
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Price (GH₵)</Label>
                        <Input className="mt-1" value={createForm.data.price_ghs} onChange={(e) => createForm.setData('price_ghs', e.target.value)} />
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-semibold text-gray-800">Do you want to collect any extra information?</p>
                        <div className="space-y-2">
                            {createForm.data.fields.map((field, index) => (
                                <div key={index} className="flex flex-wrap items-center gap-2">
                                    <Input
                                        placeholder="Name of field"
                                        value={field.label}
                                        onChange={(e) => {
                                            const next = [...createForm.data.fields];
                                            next[index] = { ...next[index], label: e.target.value };
                                            createForm.setData('fields', next);
                                        }}
                                    />
                                    <Input
                                        placeholder="e.g ID Number"
                                        value={field.placeholder}
                                        onChange={(e) => {
                                            const next = [...createForm.data.fields];
                                            next[index] = { ...next[index], placeholder: e.target.value };
                                            createForm.setData('fields', next);
                                        }}
                                    />
                                    <button
                                        type="button"
                                        className="rounded-lg bg-red-500 p-2 text-white"
                                        onClick={() =>
                                            createForm.setData(
                                                'fields',
                                                createForm.data.fields.filter((_, i) => i !== index),
                                            )
                                        }
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            className="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-sky-600"
                            onClick={() =>
                                createForm.setData('fields', [
                                    ...createForm.data.fields,
                                    { label: '', placeholder: '', type: fieldTypes[0] ?? 'text', required: true },
                                ])
                            }
                        >
                            <Plus className="h-4 w-4" /> Add another Field
                        </button>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700" disabled={createForm.processing}>
                            Create
                        </Button>
                    </div>
                </form>

                <div className="space-y-3">
                    {services.map((service) => (
                        <div key={service.id} className="rounded-2xl border border-gray-100 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-bold text-gray-900">{service.name}</h3>
                                    <p className="text-sm text-orange-600">{formatPrice(service.price_ghs)}</p>
                                    <p className="text-xs text-gray-500">{service.active ? 'Active' : 'Inactive'} · {service.fields.length} fields</p>
                                </div>
                                <Button type="button" variant="outline" onClick={() => startEdit(service)}>
                                    Edit
                                </Button>
                            </div>

                            {editingId === service.id ? (
                                <form onSubmit={submitEdit} className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                                    <Input value={editForm.data.price_ghs} onChange={(e) => editForm.setData('price_ghs', e.target.value)} />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={editForm.data.active}
                                            onChange={(e) => editForm.setData('active', e.target.checked)}
                                        />
                                        Active
                                    </label>
                                    {editForm.data.fields.map((field, index) => (
                                        <div key={field.id ?? index} className="flex flex-wrap gap-2">
                                            <Input
                                                placeholder="Name of field"
                                                value={field.label}
                                                onChange={(e) => {
                                                    const next = [...editForm.data.fields];
                                                    next[index] = { ...next[index], label: e.target.value };
                                                    editForm.setData('fields', next);
                                                }}
                                            />
                                            <Input
                                                placeholder="e.g ID Number"
                                                value={field.placeholder}
                                                onChange={(e) => {
                                                    const next = [...editForm.data.fields];
                                                    next[index] = { ...next[index], placeholder: e.target.value };
                                                    editForm.setData('fields', next);
                                                }}
                                            />
                                            <button
                                                type="button"
                                                className="rounded-lg bg-red-500 p-2 text-white"
                                                onClick={() =>
                                                    editForm.setData(
                                                        'fields',
                                                        editForm.data.fields.filter((_, i) => i !== index),
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 text-sm font-semibold text-sky-600"
                                        onClick={() =>
                                            editForm.setData('fields', [
                                                ...editForm.data.fields,
                                                { label: '', placeholder: '', type: 'text', required: true, active: true },
                                            ])
                                        }
                                    >
                                        <Plus className="h-4 w-4" /> Add another Field
                                    </button>
                                    <div className="flex gap-2">
                                        <Button type="button" variant="outline" onClick={() => setEditingId(null)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={editForm.processing}>
                                            Save
                                        </Button>
                                    </div>
                                </form>
                            ) : null}
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
