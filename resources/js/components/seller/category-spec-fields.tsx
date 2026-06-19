import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Category, SpecField } from '@/types/marketplace';

interface CategorySpecFieldsProps {
    categoryId: string;
    categories: Category[];
    specifications: Record<string, string>;
    onChange: (specs: Record<string, string>) => void;
    errors?: Record<string, string>;
}

export default function CategorySpecFields({
    categoryId,
    categories,
    specifications,
    onChange,
    errors,
}: CategorySpecFieldsProps) {
    const category = categories.find((c) => c.id.toString() === categoryId);
    const fields: SpecField[] = category?.spec_schema?.fields ?? [];

    if (!category || fields.length === 0) {
        return null;
    }

    const updateField = (key: string, value: string) => {
        onChange({ ...specifications, [key]: value });
    };

    return (
        <div className="rounded-xl border border-orange-100 bg-orange-50/50 p-4">
            <div className="mb-4 flex items-center gap-2">
                {category.icon && (
                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-xl shadow-sm">
                        {category.icon}
                    </span>
                )}
                <div>
                    <h3 className="font-semibold text-gray-900">{category.name} Specifications</h3>
                    <p className="text-xs text-gray-500">Fill in details buyers look for in this category</p>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {fields.map((field) => (
                    <div key={field.key}>
                        <Label>{field.label}</Label>
                        {field.type === 'select' && field.options ? (
                            <select
                                value={specifications[field.key] ?? ''}
                                onChange={(e) => updateField(field.key, e.target.value)}
                                className="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm"
                            >
                                <option value="">Select {field.label.toLowerCase()}</option>
                                {field.options.map((opt) => (
                                    <option key={opt} value={opt}>{opt}</option>
                                ))}
                            </select>
                        ) : (
                            <Input
                                value={specifications[field.key] ?? ''}
                                onChange={(e) => updateField(field.key, e.target.value)}
                                placeholder={field.label}
                                className="mt-1 bg-white"
                            />
                        )}
                        <InputError message={errors?.[`specifications.${field.key}`]} />
                    </div>
                ))}
            </div>
        </div>
    );
}
