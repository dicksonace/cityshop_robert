import { Link } from '@inertiajs/react';

export type AdminOrderTab =
    | 'all'
    | 'unprocessed'
    | 'awaiting-direct'
    | 'confirm-delivery'
    | 'cancellations';

const tabs: { key: AdminOrderTab; label: string; href: () => string }[] = [
    { key: 'all', label: 'All orders', href: () => route('admin.orders.index') },
    { key: 'unprocessed', label: 'Unprocessed 24h+', href: () => route('admin.orders.unprocessed') },
    { key: 'awaiting-direct', label: 'Awaiting direct payment', href: () => route('admin.orders.awaiting-direct') },
    { key: 'confirm-delivery', label: 'Confirm delivery', href: () => route('admin.orders.confirm-delivery') },
    { key: 'cancellations', label: 'Seller cancellations', href: () => route('admin.orders.cancellations') },
];

export default function AdminOrderTabs({ active }: { active: AdminOrderTab }) {
    return (
        <div className="mb-4 flex flex-wrap gap-2">
            {tabs.map((tab) =>
                tab.key === active ? (
                    <span key={tab.key} className="rounded-full bg-orange-500 px-4 py-1.5 text-sm font-medium text-white">
                        {tab.label}
                    </span>
                ) : (
                    <Link
                        key={tab.key}
                        href={tab.href()}
                        className="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-gray-600 shadow-sm"
                    >
                        {tab.label}
                    </Link>
                ),
            )}
        </div>
    );
}
