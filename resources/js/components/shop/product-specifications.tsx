import { Category, SpecField } from '@/types/marketplace';

interface ProductSpecificationsProps {
    category?: Category | null;
    specifications?: Record<string, string> | null;
}

function labelForKey(key: string, fields: SpecField[]): string {
    return fields.find((f) => f.key === key)?.label ?? key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ProductSpecifications({ category, specifications }: ProductSpecificationsProps) {
    const fields = category?.spec_schema?.fields ?? [];
    const entries = Object.entries(specifications ?? {}).filter(([, v]) => v);

    if (entries.length === 0) {
        return null;
    }

    return (
        <section className="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center gap-3">
                {category?.icon && (
                    <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-2xl">
                        {category.icon}
                    </span>
                )}
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Specifications</h2>
                    {category && (
                        <p className="text-sm text-gray-500">{category.name} details</p>
                    )}
                </div>
            </div>

            <dl className="divide-y divide-gray-100">
                {entries.map(([key, value]) => (
                    <div key={key} className="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4">
                        <dt className="min-w-[140px] text-sm font-medium text-gray-500">
                            {labelForKey(key, fields)}
                        </dt>
                        <dd className="text-sm font-medium text-gray-900">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
