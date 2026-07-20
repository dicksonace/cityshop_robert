import { router } from '@inertiajs/react';
import {
    Heart,
    Laptop,
    MapPin,
    Monitor,
    Package,
    Phone,
    Shirt,
    ShoppingBag,
    Smartphone,
    Sparkles,
    Truck,
    Home as HomeIcon,
    Car,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { applyFilters, ShopFilters } from '@/components/shop/product-filters';
import { cn } from '@/lib/utils';

interface Category {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface HomeCategoryShortcutsProps {
    categories: Category[];
    filters: ShopFilters;
    counts: { in_ghana: number; free_ship: number; total: number };
    hasSaleProducts?: boolean;
}

function categoryIcon(slug: string, name: string): LucideIcon {
    const key = `${slug} ${name}`.toLowerCase();
    if (key.includes('phone') || key.includes('tablet')) return Smartphone;
    if (key.includes('computer') || key.includes('laptop')) return Laptop;
    if (key.includes('electronic')) return Monitor;
    if (key.includes('vehicle') || key.includes('car') || key.includes('bike')) return Car;
    if (key.includes('home') || key.includes('garden') || key.includes('furniture')) return HomeIcon;
    if (key.includes('fashion') || key.includes('cloth') || key.includes('dress')) return Shirt;
    if (key.includes('earphone') || key.includes('audio') || key.includes('headphone')) return Phone;
    return Package;
}

const iconColors = [
    'bg-rose-100 text-rose-600',
    'bg-sky-100 text-sky-600',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-600',
    'bg-violet-100 text-violet-600',
    'bg-blue-100 text-blue-600',
    'bg-orange-100 text-orange-600',
    'bg-teal-100 text-teal-600',
];

export default function HomeCategoryShortcuts({
    categories,
    filters,
    counts,
    hasSaleProducts = false,
}: HomeCategoryShortcutsProps) {
    const quickLinks = [
        {
            key: 'wishlist',
            label: 'Wish List',
            icon: Heart,
            color: 'bg-pink-100 text-pink-600',
            onClick: () => router.visit(route('wishlist.index')),
            active: false,
        },
        {
            key: 'in_ghana',
            label: 'In Ghana',
            icon: MapPin,
            color: 'bg-emerald-100 text-emerald-700',
            onClick: () => applyFilters({ in_ghana: !filters.in_ghana }, filters),
            active: Boolean(filters.in_ghana),
            count: counts.in_ghana,
        },
        {
            key: 'newest',
            label: 'New Arrival',
            icon: Sparkles,
            color: 'bg-orange-100 text-orange-600',
            onClick: () => applyFilters({ sort: 'newest' }, filters),
            active: filters.sort === 'newest',
        },
        {
            key: 'free_ship',
            label: 'Free Delivery',
            icon: Truck,
            color: 'bg-blue-100 text-blue-600',
            onClick: () => applyFilters({ free_ship: !filters.free_ship }, filters),
            active: Boolean(filters.free_ship),
            count: counts.free_ship,
        },
    ];

    const categoryLinks = categories.slice(0, 4).map((cat, index) => ({
        key: `cat-${cat.id}`,
        label: cat.name,
        icon: categoryIcon(cat.slug, cat.name),
        color: iconColors[index % iconColors.length],
        onClick: () => applyFilters({ category: String(cat.id) }, filters),
        active: String(filters.category) === String(cat.id),
        count: cat.products_count,
    }));

    const tiles = [...quickLinks, ...categoryLinks];

    return (
        <section className="border-b border-gray-100 bg-white">
            <div className="mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-5">
                <div className="grid grid-cols-4 gap-2 sm:gap-4">
                    {tiles.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={item.onClick}
                            className={cn(
                                'flex flex-col items-center gap-1.5 rounded-2xl p-1.5 text-center transition',
                                item.active ? 'bg-orange-50 ring-2 ring-orange-300' : 'hover:bg-gray-50',
                            )}
                        >
                            <span className={cn('flex h-12 w-12 items-center justify-center rounded-full sm:h-14 sm:w-14', item.color)}>
                                <item.icon className="h-5 w-5 sm:h-6 sm:w-6" />
                            </span>
                            <span className="line-clamp-2 max-w-[4.75rem] text-[10px] font-semibold leading-tight text-gray-800 sm:max-w-none sm:text-xs">
                                {item.label}
                            </span>
                        </button>
                    ))}
                </div>

                {hasSaleProducts && (
                    <button
                        type="button"
                        onClick={() => applyFilters({ sort: 'popular' }, filters)}
                        className="mt-4 flex w-full items-center justify-between overflow-hidden rounded-2xl bg-gradient-to-r from-amber-400 via-orange-400 to-rose-400 px-4 py-3 text-left shadow-sm sm:px-5 sm:py-4"
                    >
                        <div>
                            <p className="text-[10px] font-bold tracking-wider text-white/90 uppercase sm:text-xs">Big Sale</p>
                            <p className="text-sm font-extrabold text-white sm:text-lg">Shop top deals on CityShop</p>
                        </div>
                        <span className="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-orange-600 shadow-sm">
                            <ShoppingBag className="h-3.5 w-3.5" />
                            Shop Now
                        </span>
                    </button>
                )}
            </div>
        </section>
    );
}
