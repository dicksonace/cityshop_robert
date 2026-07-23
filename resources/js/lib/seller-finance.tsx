import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import { Paginated } from '@/types/marketplace';

export function formatFinanceDate(value?: string | null, withTime = true): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

export function PaginationLinks({
    links,
    className,
}: {
    links: Paginated<unknown>['links'];
    className?: string;
}) {
    if (links.length <= 3) return null;

    return (
        <div className={cn('mt-6 flex flex-wrap gap-2', className)}>
            {links.map((link, i) => (
                <Link
                    key={i}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={cn(
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        link.active
                            ? 'bg-orange-500 text-white'
                            : link.url
                              ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                              : 'cursor-not-allowed bg-gray-50 text-gray-400',
                    )}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export const withdrawalStatusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
    processing: 'bg-blue-100 text-blue-800 ring-1 ring-blue-200',
    approved: 'bg-blue-100 text-blue-800 ring-1 ring-blue-200',
    paid: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
    rejected: 'bg-red-100 text-red-800 ring-1 ring-red-200',
};

export const withdrawalStatusLabel: Record<string, string> = {
    pending: 'Processing',
    processing: 'Processing',
    approved: 'Approved',
    paid: 'Paid out',
    rejected: 'Rejected',
};

export function transactionTypeBadgeClass(type: string): string {
    switch (type) {
        case 'sale_pending':
            return 'bg-amber-100 text-amber-900 ring-1 ring-amber-200';
        case 'sale_released':
        case 'fund_added':
        case 'withdrawal_refunded':
        case 'order_refund':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200';
        case 'withdrawal':
        case 'withdrawal_completed':
        case 'order_payment':
        case 'fund_removed':
        case 'direct_cancel_debit':
            return 'bg-rose-100 text-rose-800 ring-1 ring-rose-200';
        case 'sale_reversed':
            return 'bg-red-100 text-red-800 ring-1 ring-red-200';
        default:
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200';
    }
}
