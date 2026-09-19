import { Plus, Trash2 } from 'lucide-react';

import { Input } from '@/components/ui/input';

export type BuyerFieldRow = {
    key?: string;
    label: string;
    placeholder: string;
    type: string;
    required: boolean;
};

interface Props {
    fields: BuyerFieldRow[];
    onChange: (fields: BuyerFieldRow[]) => void;
}

export default function BuyerFieldBuilder({ fields, onChange }: Props) {
    const rows = fields.length > 0 ? fields : [{ label: '', placeholder: '', type: 'text', required: true }];

    const update = (index: number, patch: Partial<BuyerFieldRow>) => {
        const next = rows.map((row, i) => (i === index ? { ...row, ...patch } : row));
        onChange(next);
    };

    return (
        <div className="space-y-3 rounded-xl border border-orange-100 bg-orange-50/40 p-4">
            <div>
                <p className="text-sm font-semibold text-gray-900">Do you want to collect any extra information?</p>
                <p className="mt-0.5 text-xs text-gray-600">
                    Buyers fill these when they order — IMEI, ID number, Alipay ID, Ghana Card, etc. Works for GSM
                    Tools, RMB, and regular Ghana products.
                </p>
            </div>
            <div className="space-y-2">
                {rows.map((field, index) => (
                    <div key={index} className="flex flex-wrap items-center gap-2">
                        <Input
                            placeholder="Name of field"
                            value={field.label}
                            onChange={(e) => update(index, { label: e.target.value })}
                            className="min-w-[140px] flex-1 bg-white"
                        />
                        <Input
                            placeholder="e.g ID Number"
                            value={field.placeholder}
                            onChange={(e) => update(index, { placeholder: e.target.value })}
                            className="min-w-[140px] flex-1 bg-white"
                        />
                        <button
                            type="button"
                            className="rounded-lg bg-red-500 p-2 text-white"
                            onClick={() => onChange(rows.filter((_, i) => i !== index))}
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
            <button
                type="button"
                className="inline-flex items-center gap-1 text-sm font-semibold text-sky-600"
                onClick={() => onChange([...rows, { label: '', placeholder: '', type: 'text', required: true }])}
            >
                <Plus className="h-4 w-4" /> Add another Field
            </button>
        </div>
    );
}
