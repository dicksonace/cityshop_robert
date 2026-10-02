import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search, Trash2 } from 'lucide-react';
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
    overview: string | null;
    features: string[];
    what_to_send: string | null;
    eta_label: string;
    allow_quantity: boolean;
    min_qty: number;
    max_qty: number;
    gsm_service_group_id?: number | null;
    group_id: number | null;
    group_name: string | null;
    image_url: string | null;
    service_type: string;
    service_type_label: string;
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

type Group = { id: number; name: string; image_url: string | null };

type ServiceType = { value: string; label: string };

interface Props {
    services: Service[];
    groups: Group[];
    fieldTypes: string[];
    serviceTypes: ServiceType[];
    selectedType: string;
    active: 'gsm-tools-imei' | 'gsm-tools-server' | 'gsm-tools-remote' | 'gsm-tools-file' | 'gsm-tools-credit';
}

const FIELD_TYPE_LABELS: Record<string, string> = {
    text: 'Text',
    textarea: 'Long text',
    number: 'Number',
    phone: 'Mobile',
    email: 'Email',
    password: 'Password',
    url: 'Link',
    image: 'Photo',
};

const FIELD_PRESETS: Array<{ label: string; placeholder: string; type: string; required: boolean }> = [
    { label: 'Username', placeholder: 'Username', type: 'text', required: true },
    { label: 'Password', placeholder: 'Password', type: 'password', required: false },
    { label: 'Mobile', placeholder: 'Mobile', type: 'phone', required: true },
    { label: 'Email', placeholder: 'Email', type: 'email', required: true },
    { label: 'IMEI', placeholder: 'IMEI', type: 'text', required: true },
    { label: 'Serial', placeholder: 'serial', type: 'text', required: true },
];

function FieldInputs({
    field,
    fieldTypes,
    onChange,
    onRemove,
}: {
    field: FieldRow;
    fieldTypes: string[];
    onChange: (next: FieldRow) => void;
    onRemove: () => void;
}) {
    return (
        <div className="space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    placeholder="Name of field (Username, Password…)"
                    value={field.label}
                    onChange={(e) => onChange({ ...field, label: e.target.value })}
                />
                <Input
                    placeholder="Placeholder shown to buyer"
                    value={field.placeholder}
                    onChange={(e) => onChange({ ...field, placeholder: e.target.value })}
                />
                <select
                    className="h-9 rounded-md border border-gray-200 bg-white px-2 text-sm"
                    value={field.type}
                    onChange={(e) => onChange({ ...field, type: e.target.value })}
                >
                    {fieldTypes.map((type) => (
                        <option key={type} value={type}>
                            {FIELD_TYPE_LABELS[type] ?? type}
                        </option>
                    ))}
                </select>
                <label className="flex items-center gap-1 text-xs font-semibold text-gray-700">
                    <input
                        type="checkbox"
                        checked={field.required}
                        onChange={(e) => onChange({ ...field, required: e.target.checked })}
                    />
                    Required
                </label>
                <button type="button" className="rounded-lg bg-red-500 p-2 text-white" onClick={onRemove}>
                    <Trash2 className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

function QuantitySettings({
    allowQuantity,
    minQty,
    maxQty,
    onAllow,
    onMin,
    onMax,
}: {
    allowQuantity: boolean;
    minQty: string;
    maxQty: string;
    onAllow: (value: boolean) => void;
    onMin: (value: string) => void;
    onMax: (value: string) => void;
}) {
    return (
        <div className="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
            <label className="flex items-start gap-2 text-sm font-semibold text-gray-800">
                <input type="checkbox" className="mt-0.5" checked={allowQuantity} onChange={(e) => onAllow(e.target.checked)} />
                <span>
                    This service uses quantity
                    <span className="block text-xs font-normal text-gray-500">Leave off unless the buyer should order more than one (credits, IMEI lots, etc.).</span>
                </span>
            </label>
            {allowQuantity ? (
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Minimum quantity</Label>
                        <Input className="mt-1" inputMode="numeric" value={minQty} onChange={(e) => onMin(e.target.value)} />
                    </div>
                    <div>
                        <Label>Maximum quantity</Label>
                        <Input className="mt-1" inputMode="numeric" value={maxQty} onChange={(e) => onMax(e.target.value)} />
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export default function AdminGsmServices({ services, groups, fieldTypes, serviceTypes, selectedType, active }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [editingId, setEditingId] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const visibleServices = needle
        ? services.filter((service) =>
              `${service.name} ${service.description ?? ''} ${service.group_name ?? ''}`.toLowerCase().includes(needle),
          )
        : services;
    const current = serviceTypes.find((type) => type.value === selectedType) ?? serviceTypes[0];

    const groupForm = useForm({
        name: '',
        service_type: selectedType,
        image: null as File | null,
    });

    const createForm = useForm({
        name: '',
        service_type: selectedType,
        gsm_service_group_id: '',
        description: '',
        overview: '',
        features: '',
        what_to_send: '',
        eta_label: 'INSTANT',
        allow_quantity: false,
        min_qty: '1',
        max_qty: '10',
        price_ghs: '50',
        sort_order: '0',
        active: true,
        image: null as File | null,
        fields: [] as FieldRow[],
    });

    const editForm = useForm({
        name: '',
        service_type: 'imei',
        gsm_service_group_id: '',
        description: '',
        overview: '',
        features: '',
        what_to_send: '',
        eta_label: 'INSTANT',
        allow_quantity: false,
        min_qty: '1',
        max_qty: '10',
        price_ghs: '',
        sort_order: '0',
        active: true,
        image: null as File | null,
        fields: [] as FieldRow[],
    });

    const submitGroup: FormEventHandler = (e) => {
        e.preventDefault();
        groupForm.transform((data) => ({ ...data, service_type: selectedType }));
        groupForm.post(route('admin.gsm-tools.groups.store'), {
            forceFormData: true,
            onSuccess: () => groupForm.reset('name', 'image'),
        });
    };

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.transform((data) => {
            const next = { ...data, service_type: selectedType };
            if (!next.image) {
                delete (next as { image?: File | null }).image;
            }
            return next;
        });
        createForm.post(route('admin.gsm-tools.services.store'), {
            forceFormData: true,
            onSuccess: () => createForm.reset('name', 'description', 'overview', 'features', 'what_to_send', 'image'),
        });
    };

    const startEdit = (service: Service) => {
        setEditingId(service.id);
        editForm.setData({
            name: service.name,
            service_type: service.service_type || 'imei',
            gsm_service_group_id: service.group_id ? String(service.group_id) : '',
            description: service.description ?? '',
            overview: service.overview ?? '',
            features: (service.features ?? []).join('\n'),
            what_to_send: service.what_to_send ?? '',
            eta_label: service.eta_label || 'INSTANT',
            allow_quantity: !!service.allow_quantity,
            min_qty: String(service.min_qty || 1),
            max_qty: String(service.max_qty || 10),
            price_ghs: String(service.price_ghs),
            sort_order: String(service.sort_order),
            active: service.active,
            image: null,
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
        editForm.transform((data) => {
            const next = { ...data };
            if (!next.image) {
                delete (next as { image?: File | null }).image;
            }
            return next;
        });
        editForm.post(route('admin.gsm-tools.services.update', editingId), {
            forceFormData: true,
            onSuccess: () => setEditingId(null),
        });
    };

    return (
        <AdminLayout title={current?.label ?? 'GSM Services'} active={active}>
            <Head title={current?.label ?? 'GSM Services'} />
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-orange-500">GSM Tools</p>
                        <h1 className="text-xl font-bold text-gray-900">{current?.label ?? 'GSM Services'}</h1>
                        <p className="text-sm text-gray-500">Add tools in this group. Extra fields are what the buyer fills when they order.</p>
                    </div>
                    <button type="button" className="text-sm text-orange-600" onClick={() => router.visit(route('admin.gsm-tools.index'))}>
                        ← All orders
                    </button>
                </div>

                <div className="space-y-1 rounded-2xl bg-slate-800 p-3 text-white">
                    <p className="px-2 pb-1 text-sm font-semibold">Service</p>
                    {serviceTypes.map((type) => (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() => router.get(route('admin.gsm-tools.services'), { type: type.value })}
                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm ${
                                selectedType === type.value ? 'bg-slate-700' : 'hover:bg-slate-700/60'
                            }`}
                        >
                            <span className={`h-3.5 w-3.5 rounded-full border ${selectedType === type.value ? 'border-emerald-400 bg-emerald-400' : 'border-white/50'}`} />
                            {type.label}
                        </button>
                    ))}
                </div>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <form onSubmit={submitGroup} className="space-y-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="font-bold text-gray-900">Categories (like Galaxy Multi Tool, T-Mobile)</h2>
                    <p className="text-sm text-gray-500">Buyers see these groups with logos on Place order.</p>
                    <Input placeholder="Category name" value={groupForm.data.name} onChange={(e) => groupForm.setData('name', e.target.value)} />
                    <div>
                        <Label>Category logo</Label>
                        <input
                            className="mt-1 block w-full text-sm"
                            type="file"
                            accept="image/*"
                            onChange={(e) => groupForm.setData('image', e.target.files?.[0] ?? null)}
                        />
                    </div>
                    <Button type="submit" className="bg-slate-800 hover:bg-slate-900" disabled={groupForm.processing}>
                        Add category
                    </Button>
                    {groups.length > 0 ? (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {groups.map((group) => (
                                <li key={group.id} className="flex items-center gap-2 py-2">
                                    {group.image_url ? (
                                        <img src={group.image_url} alt="" className="h-9 w-9 rounded-lg bg-slate-950 object-contain" />
                                    ) : (
                                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-[10px] font-bold text-orange-600">
                                            CAT
                                        </span>
                                    )}
                                    <span className="min-w-0 flex-1 font-semibold text-gray-900">{group.name}</span>
                                    <button
                                        type="button"
                                        className="rounded-lg border border-red-200 px-2 py-1 text-xs font-bold text-red-600 hover:bg-red-50"
                                        onClick={() => {
                                            if (
                                                !window.confirm(
                                                    `Delete category “${group.name}”? Services in it stay, but they will have no category.`,
                                                )
                                            ) {
                                                return;
                                            }
                                            router.delete(route('admin.gsm-tools.groups.destroy', group.id), { preserveScroll: true });
                                        }}
                                    >
                                        Delete
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </form>

                <form onSubmit={submitCreate} className="space-y-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="font-bold text-gray-900">New {current?.label ?? 'service'}</h2>
                    <div>
                        <Label>Category</Label>
                        <select
                            className="mt-1 h-10 w-full rounded-md border border-gray-200 bg-white px-2 text-sm"
                            value={createForm.data.gsm_service_group_id}
                            onChange={(e) => createForm.setData('gsm_service_group_id', e.target.value)}
                        >
                            <option value="">No category</option>
                            {groups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label>Name</Label>
                        <Input className="mt-1" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                        <InputError message={createForm.errors.name} />
                    </div>
                    <div>
                        <Label>Service description</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={3}
                            placeholder="What this service does"
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Delivery time</Label>
                        <Input
                            className="mt-1"
                            placeholder="INSTANT, 1-24 hours, 1-3 days…"
                            value={createForm.data.eta_label}
                            onChange={(e) => createForm.setData('eta_label', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-500">Shown to the buyer on Place order.</p>
                    </div>
                    <div>
                        <Label>Price (GH₵)</Label>
                        <Input className="mt-1" value={createForm.data.price_ghs} onChange={(e) => createForm.setData('price_ghs', e.target.value)} />
                    </div>
                    <QuantitySettings
                        allowQuantity={createForm.data.allow_quantity}
                        minQty={createForm.data.min_qty}
                        maxQty={createForm.data.max_qty}
                        onAllow={(value) => createForm.setData('allow_quantity', value)}
                        onMin={(value) => createForm.setData('min_qty', value)}
                        onMax={(value) => createForm.setData('max_qty', value)}
                    />
                    <div>
                        <Label>Overview (shown on Place order)</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={3}
                            value={createForm.data.overview}
                            onChange={(e) => createForm.setData('overview', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Key features (one per line)</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={3}
                            value={createForm.data.features}
                            onChange={(e) => createForm.setData('features', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>What you need to send</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={2}
                            value={createForm.data.what_to_send}
                            onChange={(e) => createForm.setData('what_to_send', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Service logo</Label>
                        <p className="mt-0.5 text-xs text-gray-500">Shown on web and in the app next to this service.</p>
                        <input
                            type="file"
                            accept="image/*"
                            className="mt-1 block w-full text-sm"
                            onChange={(e) => createForm.setData('image', e.target.files?.[0] ?? null)}
                        />
                    </div>

                    <div>
                        <p className="mb-1 text-sm font-semibold text-gray-800">What should the buyer submit?</p>
                        <p className="mb-2 text-xs text-gray-500">
                            Each service has its own form. Add Username, Password, Mobile, Email, IMEI — whatever this tool needs.
                        </p>
                        <div className="mb-2 flex flex-wrap gap-1.5">
                            {FIELD_PRESETS.map((preset) => (
                                <button
                                    key={preset.label}
                                    type="button"
                                    className="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700"
                                    onClick={() =>
                                        createForm.setData('fields', [
                                            ...createForm.data.fields,
                                            { ...preset },
                                        ])
                                    }
                                >
                                    + {preset.label}
                                </button>
                            ))}
                        </div>
                        <div className="space-y-2">
                            {createForm.data.fields.map((field, index) => (
                                <FieldInputs
                                    key={index}
                                    field={field}
                                    fieldTypes={fieldTypes}
                                    onChange={(nextField) => {
                                        const next = [...createForm.data.fields];
                                        next[index] = nextField;
                                        createForm.setData('fields', next);
                                    }}
                                    onRemove={() =>
                                        createForm.setData(
                                            'fields',
                                            createForm.data.fields.filter((_, i) => i !== index),
                                        )
                                    }
                                />
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
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search services"
                            className="pl-9"
                        />
                    </div>
                    {services.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                            No {current?.label ?? 'services'} yet. Add the first one here. More details can be filled in later.
                        </p>
                    ) : visibleServices.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                            No services match that search.
                        </p>
                    ) : null}
                    {visibleServices.map((service) => (
                        <div key={service.id} className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div className="flex items-start gap-3">
                                {service.image_url ? (
                                    <img
                                        src={service.image_url}
                                        alt=""
                                        className="h-14 w-14 shrink-0 rounded-2xl bg-slate-950 object-contain"
                                    />
                                ) : (
                                    <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-[10px] font-bold text-orange-600">
                                        GSM
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-base font-black text-gray-900">{service.name}</h3>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        <span className="rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-extrabold text-emerald-800">
                                            {formatPrice(service.price_ghs)}
                                        </span>
                                        <span className="rounded-md bg-blue-50 px-2 py-1 text-[11px] font-extrabold uppercase text-blue-700">
                                            {service.eta_label || 'INSTANT'}
                                        </span>
                                        <span className="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-extrabold uppercase text-gray-700">
                                            {service.allow_quantity ? `Qty ${service.min_qty}–${service.max_qty}` : 'No quantity'}
                                        </span>
                                        <span
                                            className={`rounded-md px-2 py-1 text-[11px] font-extrabold uppercase ${service.active ? 'bg-orange-50 text-orange-700' : 'bg-red-50 text-red-700'}`}
                                        >
                                            {service.active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    {service.fields.length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {service.fields.map((field) => (
                                                <span
                                                    key={field.id}
                                                    className="rounded-md bg-orange-50 px-2 py-1 text-[11px] font-extrabold text-orange-800"
                                                >
                                                    {field.label}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null}
                                    {service.description ? (
                                        <p className="mt-2 line-clamp-2 text-sm text-gray-600">{service.description}</p>
                                    ) : null}
                                </div>
                            </div>
                            <div className="mt-3 flex gap-2">
                                <Button type="button" className="flex-1 bg-orange-600 hover:bg-orange-700" onClick={() => startEdit(service)}>
                                    Edit
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="border-red-200 text-red-600 hover:bg-red-50"
                                    onClick={() => {
                                        if (!window.confirm(`Delete “${service.name}”? Buyers will no longer see it. Existing orders stay in history.`)) {
                                            return;
                                        }
                                        router.delete(route('admin.gsm-tools.services.destroy', service.id), {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                if (editingId === service.id) {
                                                    setEditingId(null);
                                                }
                                            },
                                        });
                                    }}
                                >
                                    Delete
                                </Button>
                            </div>

                            {editingId === service.id ? (
                                <form onSubmit={submitEdit} className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                                    <div>
                                        <Label>Service description</Label>
                                        <textarea
                                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                            rows={3}
                                            value={editForm.data.description}
                                            onChange={(e) => editForm.setData('description', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label>Delivery time</Label>
                                        <Input
                                            className="mt-1"
                                            value={editForm.data.eta_label}
                                            onChange={(e) => editForm.setData('eta_label', e.target.value)}
                                        />
                                    </div>
                                    <select
                                        className="h-9 w-full rounded-md border border-gray-200 bg-white px-2 text-sm"
                                        value={editForm.data.gsm_service_group_id}
                                        onChange={(e) => editForm.setData('gsm_service_group_id', e.target.value)}
                                    >
                                        <option value="">No category</option>
                                        {groups.map((group) => (
                                            <option key={group.id} value={group.id}>
                                                {group.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className="h-9 w-full rounded-md border border-gray-200 bg-white px-2 text-sm"
                                        value={editForm.data.service_type}
                                        onChange={(e) => editForm.setData('service_type', e.target.value)}
                                    >
                                        {serviceTypes.map((type) => (
                                            <option key={type.value} value={type.value}>
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                    <Input
                                        value={editForm.data.price_ghs}
                                        onChange={(e) => editForm.setData('price_ghs', e.target.value)}
                                        placeholder="Price (GH₵)"
                                    />
                                    <QuantitySettings
                                        allowQuantity={editForm.data.allow_quantity}
                                        minQty={editForm.data.min_qty}
                                        maxQty={editForm.data.max_qty}
                                        onAllow={(value) => editForm.setData('allow_quantity', value)}
                                        onMin={(value) => editForm.setData('min_qty', value)}
                                        onMax={(value) => editForm.setData('max_qty', value)}
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={editForm.data.active}
                                            onChange={(e) => editForm.setData('active', e.target.checked)}
                                        />
                                        Active
                                    </label>
                                    <div>
                                        <Label>Service logo</Label>
                                        {service.image_url ? (
                                            <img
                                                src={service.image_url}
                                                alt=""
                                                className="mt-1 h-14 w-14 rounded-xl bg-slate-950 object-contain"
                                            />
                                        ) : null}
                                        <input
                                            className="mt-2 block w-full text-sm"
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => editForm.setData('image', e.target.files?.[0] ?? null)}
                                        />
                                    </div>
                                    <p className="text-sm font-semibold text-gray-800">Buyer form fields</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {FIELD_PRESETS.map((preset) => (
                                            <button
                                                key={preset.label}
                                                type="button"
                                                className="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700"
                                                onClick={() =>
                                                    editForm.setData('fields', [
                                                        ...editForm.data.fields,
                                                        { ...preset, active: true },
                                                    ])
                                                }
                                            >
                                                + {preset.label}
                                            </button>
                                        ))}
                                    </div>
                                    {editForm.data.fields.map((field, index) => (
                                        <FieldInputs
                                            key={field.id ?? index}
                                            field={field}
                                            fieldTypes={fieldTypes}
                                            onChange={(nextField) => {
                                                const next = [...editForm.data.fields];
                                                next[index] = nextField;
                                                editForm.setData('fields', next);
                                            }}
                                            onRemove={() =>
                                                editForm.setData(
                                                    'fields',
                                                    editForm.data.fields.filter((_, i) => i !== index),
                                                )
                                            }
                                        />
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
