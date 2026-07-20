import { router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Star, X } from 'lucide-react';
import { ReactNode, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { formatPrice } from '@/types/marketplace';

export interface ShopFilters {
    search?: string;
    category?: string;
    brand?: string;
    price_min?: string;
    price_max?: string;
    rating?: string;
    in_ghana?: boolean;
    free_ship?: boolean;
    sort?: string;
    seed?: string;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface Brand {
    brand: string;
    count: number;
}

interface ProductFiltersProps {
    filters: ShopFilters;
    categories: Category[];
    brands: Brand[];
    priceRange: { min: number; max: number };
    className?: string;
}

function FilterSection({ title, defaultOpen = true, children }: { title: string; defaultOpen?: boolean; children: ReactNode }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-gray-100 py-4 last:border-0">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between text-sm font-semibold text-gray-900"
            >
                {title}
                {open ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
            </button>
            {open && <div className="mt-3 space-y-2">{children}</div>}
        </div>
    );
}

const pricePresets = [
    { label: 'Under GH₵100', min: '', max: '100' },
    { label: 'GH₵100 – GH₵500', min: '100', max: '500' },
    { label: 'GH₵500 – GH₵2,000', min: '500', max: '2000' },
    { label: 'GH₵2,000 – GH₵5,000', min: '2000', max: '5000' },
    { label: 'Over GH₵5,000', min: '5000', max: '' },
];

export function applyFilters(updates: Partial<ShopFilters>, current: ShopFilters) {
    const merged = { ...current, ...updates };
    const params: Record<string, string | number | boolean> = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (key === 'seed') return;
        if (value === '' || value === false || value === undefined) return;
        params[key] = value as string | number | boolean;
    });

    if (merged.sort === 'random' && !('sort' in updates) && current.seed) {
        params.seed = current.seed;
    }

    router.get(route('home'), params, { preserveState: true, preserveScroll: true });
}

export function clearAllFilters() {
    router.get(route('home'));
}

export default function ProductFilters({ filters, categories, brands, priceRange, className }: ProductFiltersProps) {
    const [localMin, setLocalMin] = useState(filters.price_min ?? '');
    const [localMax, setLocalMax] = useState(filters.price_max ?? '');

    const applyPrice = () => {
        applyFilters({ price_min: localMin, price_max: localMax }, filters);
    };

    const isActive = (key: keyof ShopFilters, value: string | boolean) => {
        if (typeof value === 'boolean') return filters[key] === value;
        return String(filters[key] ?? '') === String(value);
    };

    return (
        <aside className={cn('rounded-2xl border border-gray-100 bg-white p-5 shadow-sm', className)}>
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-base font-bold text-gray-900">Filters</h2>
                <button type="button" onClick={clearAllFilters} className="text-xs font-medium text-orange-500 hover:underline">
                    Clear all
                </button>
            </div>

            <FilterSection title="Department">
                <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                    <button
                        type="button"
                        onClick={() => applyFilters({ category: '' }, filters)}
                        className={cn(
                            'flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm transition-colors',
                            !filters.category
                                ? 'bg-orange-50 font-semibold text-orange-600 ring-1 ring-orange-200'
                                : 'text-gray-700 hover:bg-gray-50',
                        )}
                    >
                        <span>All Departments</span>
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">
                            {categories.reduce((sum, cat) => sum + cat.products_count, 0)}
                        </span>
                    </button>
                    {categories.map((cat) => (
                        <button
                            key={cat.id}
                            type="button"
                            onClick={() => applyFilters({ category: String(cat.id) }, filters)}
                            className={cn(
                                'flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm transition-colors',
                                isActive('category', String(cat.id))
                                    ? 'bg-orange-50 font-semibold text-orange-600 ring-1 ring-orange-200'
                                    : 'text-gray-700 hover:bg-gray-50',
                            )}
                        >
                            <span className="pr-2">{cat.name}</span>
                            <span
                                className={cn(
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                    isActive('category', String(cat.id))
                                        ? 'bg-orange-100 text-orange-700'
                                        : 'bg-gray-100 text-gray-500',
                                )}
                            >
                                {cat.products_count}
                            </span>
                        </button>
                    ))}
                    {categories.length === 0 && (
                        <p className="px-2 py-3 text-sm text-gray-400">No departments with live products yet.</p>
                    )}
                </div>
            </FilterSection>

            <FilterSection title="Price">
                <div className="flex gap-2">
                    <Input
                        type="number"
                        placeholder={`Min ${Math.floor(priceRange.min)}`}
                        value={localMin}
                        onChange={(e) => setLocalMin(e.target.value)}
                        className="h-9 text-sm"
                    />
                    <Input
                        type="number"
                        placeholder={`Max ${Math.ceil(priceRange.max)}`}
                        value={localMax}
                        onChange={(e) => setLocalMax(e.target.value)}
                        className="h-9 text-sm"
                    />
                </div>
                <Button size="sm" onClick={applyPrice} className="mt-2 w-full bg-gray-900 hover:bg-gray-800">
                    Apply
                </Button>
                <div className="mt-2 space-y-1">
                    {pricePresets.map((preset) => (
                        <button
                            key={preset.label}
                            type="button"
                            onClick={() => applyFilters({ price_min: preset.min, price_max: preset.max }, filters)}
                            className="block w-full rounded-lg px-2 py-1 text-left text-sm text-gray-600 hover:bg-gray-50 hover:text-orange-600"
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
            </FilterSection>

            <FilterSection title="Customer Reviews">
                {[4, 3, 2].map((stars) => (
                    <button
                        key={stars}
                        type="button"
                        onClick={() => applyFilters({ rating: String(stars) }, filters)}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors',
                            isActive('rating', String(stars)) ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50',
                        )}
                    >
                        <span className="flex">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Star
                                    key={i}
                                    className={cn('h-3.5 w-3.5', i < stars ? 'fill-amber-400 text-amber-400' : 'text-gray-200')}
                                />
                            ))}
                        </span>
                        <span>& Up</span>
                    </button>
                ))}
            </FilterSection>

            {brands.length > 0 && (
                <FilterSection title="Brand" defaultOpen={false}>
                    {brands.map((b) => (
                        <button
                            key={b.brand}
                            type="button"
                            onClick={() => applyFilters({ brand: b.brand }, filters)}
                            className={cn(
                                'flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-sm transition-colors',
                                isActive('brand', b.brand) ? 'bg-orange-50 font-medium text-orange-600' : 'text-gray-600 hover:bg-gray-50',
                            )}
                        >
                            <span>{b.brand}</span>
                            <span className="text-xs text-gray-400">({b.count})</span>
                        </button>
                    ))}
                </FilterSection>
            )}

            <FilterSection title="Delivery & Offers">
                {[
                    { key: 'in_ghana' as const, label: 'In Ghana', desc: 'Seller delivers locally' },
                    { key: 'free_ship' as const, label: 'Free Delivery', desc: 'Seller delivers free' },
                ].map((item) => (
                    <label
                        key={item.key}
                        className={cn(
                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                            filters[item.key] ? 'border-orange-200 bg-orange-50' : 'border-gray-100 hover:border-gray-200',
                        )}
                    >
                        <input
                            type="checkbox"
                            checked={!!filters[item.key]}
                            onChange={(e) => applyFilters({ [item.key]: e.target.checked }, filters)}
                            className="mt-0.5 accent-orange-500"
                        />
                        <div>
                            <p className="text-sm font-medium text-gray-900">{item.label}</p>
                            <p className="text-xs text-gray-500">{item.desc}</p>
                        </div>
                    </label>
                ))}
            </FilterSection>
        </aside>
    );
}

export function ActiveFilterChips({ filters, categories }: Pick<ProductFiltersProps, 'filters' | 'categories'>) {
    const chips: { label: string; onRemove: () => void }[] = [];

    if (filters.search) {
        chips.push({ label: `"${filters.search}"`, onRemove: () => applyFilters({ search: '' }, filters) });
    }
    if (filters.category) {
        const cat = categories.find((c) => String(c.id) === filters.category);
        chips.push({ label: cat?.name ?? 'Category', onRemove: () => applyFilters({ category: '' }, filters) });
    }
    if (filters.brand) {
        chips.push({ label: filters.brand, onRemove: () => applyFilters({ brand: '' }, filters) });
    }
    if (filters.price_min || filters.price_max) {
        const min = filters.price_min ? formatPrice(Number(filters.price_min)) : 'Any';
        const max = filters.price_max ? formatPrice(Number(filters.price_max)) : 'Any';
        chips.push({ label: `${min} – ${max}`, onRemove: () => applyFilters({ price_min: '', price_max: '' }, filters) });
    }
    if (filters.rating) {
        chips.push({ label: `${filters.rating}★ & up`, onRemove: () => applyFilters({ rating: '' }, filters) });
    }
    if (filters.in_ghana) chips.push({ label: 'In Ghana', onRemove: () => applyFilters({ in_ghana: false }, filters) });
    if (filters.free_ship) chips.push({ label: 'Free Delivery', onRemove: () => applyFilters({ free_ship: false }, filters) });

    if (chips.length === 0) return null;

    return (
        <div className="mb-4 flex flex-wrap items-center gap-2">
            <span className="text-sm text-gray-500">Active:</span>
            {chips.map((chip) => (
                <button
                    key={chip.label}
                    type="button"
                    onClick={chip.onRemove}
                    className="inline-flex items-center gap-1 rounded-full bg-orange-100 px-3 py-1 text-xs font-medium text-orange-700 hover:bg-orange-200"
                >
                    {chip.label}
                    <X className="h-3 w-3" />
                </button>
            ))}
        </div>
    );
}
