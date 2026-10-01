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
    overview: string | null;
    features: string[];
    what_to_send: string | null;
    eta_label: string;
    gsm_service_group_id?: number | null;
    group_id: number | null;
    group_name: string | null;
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

export default function AdminGsmServices({ services, groups, fieldTypes, serviceTypes, selectedType, active }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [editingId, setEditingId] = useState<number | null>(null);
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
        createForm.transform((data) => ({ ...data, service_type: selectedType }));
        createForm.post(route('admin.gsm-tools.services.store'), {
            forceFormData: true,
            onSuccess: () => createForm.reset('name', 'description', 'overview', 'features', 'what_to_send'),
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
                    <input type="file" accept="image/*" onChange={(e) => groupForm.setData('image', e.target.files?.[0] ?? null)} />
                    <Button type="submit" className="bg-slate-800 hover:bg-slate-900" disabled={groupForm.processing}>
                        Add category
                    </Button>
                    {groups.length > 0 ? (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {groups.map((group) => (
                                <li key={group.id} className="flex items-center gap-2 py-2">
                                    {group.image_url ? <img src={group.image_url} alt="" className="h-8 w-8 rounded object-cover" /> : null}
                                    {group.name}
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
                        <Label>Description</Label>
                        <textarea
                            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                            rows={3}
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                        />
                    </div>
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
                        <Label>Logo / image</Label>
                        <input
                            type="file"
                            accept="image/*"
                            className="mt-1 block w-full text-sm"
                            onChange={(e) => createForm.setData('image', e.target.files?.[0] ?? null)}
                        />
                    </div>
                    <div>
                        <Label>Price (GH₵)</Label>
                        <Input className="mt-1" value={createForm.data.price_ghs} onChange={(e) => createForm.setData('price_ghs', e.target.value)} />
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
                    {services.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                            No {current?.label ?? 'services'} yet. Add the first one here. More details can be filled in later.
                        </p>
                    ) : null}
                    {services.map((service) => (
                        <div key={service.id} className="rounded-2xl border border-gray-100 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-bold text-gray-900">{service.name}</h3>
                                    <p className="text-sm text-orange-600">{formatPrice(service.price_ghs)}</p>
                                    <p className="text-xs text-gray-500">
                                        {service.group_name || 'No category'} · {service.service_type_label} · {service.active ? 'Active' : 'Inactive'}
                                        {service.fields.length > 0
                                            ? ` · asks for ${service.fields.map((f) => f.label).join(', ')}`
                                            : ' · no extra fields'}
                                    </p>
                                </div>
                                <Button type="button" variant="outline" onClick={() => startEdit(service)}>
                                    Edit
                                </Button>
                            </div>

                            {editingId === service.id ? (
                                <form onSubmit={submitEdit} className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
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
                                    <Input value={editForm.data.price_ghs} onChange={(e) => editForm.setData('price_ghs', e.target.value)} />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={editForm.data.active}
                                            onChange={(e) => editForm.setData('active', e.target.checked)}
                                        />
                                        Active
                                    </label>
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
