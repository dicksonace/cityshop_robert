import { Link } from '@inertiajs/react';
import { ChevronRight, Store } from 'lucide-react';

import { cn } from '@/lib/utils';
import { SellerProfile } from '@/types/marketplace';

interface SellerStoreLinkProps {
    profile?: SellerProfile | null;
    sellerName?: string;
    className?: string;
    variant?: 'inline' | 'badge' | 'card';
}

export function storePageUrl(slug: string): string {
    return route('store.show', slug);
}

export default function SellerStoreLink({ profile, sellerName, className, variant = 'inline' }: SellerStoreLinkProps) {
    if (!profile?.slug) return null;

    const name = profile.business_name ?? profile.store_name ?? sellerName ?? 'Store';

    if (variant === 'badge') {
        return (
            <Link
                href={storePageUrl(profile.slug)}
                className={cn(
                    'inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-100',
                    className,
                )}
            >
                <Store className="h-3 w-3" />
                {name}
            </Link>
        );
    }

    if (variant === 'card') {
        return (
            <Link
                href={storePageUrl(profile.slug)}
                className={cn(
                    'flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 transition-all hover:border-orange-200 hover:bg-orange-50/50 hover:shadow-sm',
                    className,
                )}
            >
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                    {name.charAt(0).toUpperCase()}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-gray-500">Sold by</p>
                    <p className="truncate font-semibold text-gray-900">{name}</p>
                    <p className="text-xs text-orange-500">View store profile →</p>
                </div>
                <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" />
            </Link>
        );
    }

    return (
        <Link href={storePageUrl(profile.slug)} className={cn('text-orange-500 hover:underline', className)}>
            {name}
        </Link>
    );
}
