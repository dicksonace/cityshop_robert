import { Link } from '@inertiajs/react';

import { RmbTransferStatusBadge } from '@/components/china/rmb-transfer-status-badge';

type Props = {
    href: string;
    reference: string;
    subtitle: string;
    status: string;
    statusLabel: string;
    sellFlow?: boolean;
};

export function RmbTransferListItem({
    href,
    reference,
    subtitle,
    status,
    statusLabel,
    sellFlow = false,
}: Props) {
    return (
        <Link
            href={href}
            className="block rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span
                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white ${
                                sellFlow ? 'bg-gradient-to-br from-emerald-600 to-emerald-800' : 'bg-gradient-to-br from-violet-600 to-violet-800'
                            }`}
                        >
                            {sellFlow ? '↙' : '⇄'}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-bold text-gray-900">{reference}</p>
                            <p className="mt-0.5 text-sm text-gray-500">{subtitle}</p>
                        </div>
                    </div>
                </div>
                <RmbTransferStatusBadge status={status} label={statusLabel} />
            </div>
        </Link>
    );
}
