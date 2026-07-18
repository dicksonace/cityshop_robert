import { Link } from '@inertiajs/react';
import { ArrowDownToLine, History, Wallet } from 'lucide-react';

import { cn } from '@/lib/utils';
import { formatPrice } from '@/types/marketplace';

interface WalletBalanceCardProps {
    balance: number;
    pendingBalance?: number;
    withdrawHref: string;
    historyHref: string;
    className?: string;
}

export default function WalletBalanceCard({
    balance,
    pendingBalance,
    withdrawHref,
    historyHref,
    className,
}: WalletBalanceCardProps) {
    return (
        <div
            className={cn(
                'rounded-[1.75rem] border border-sky-100 bg-gradient-to-br from-sky-50/90 via-white to-slate-50 p-5 shadow-sm sm:p-6',
                className,
            )}
        >
            <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                    <Wallet className="h-5 w-5" strokeWidth={1.75} />
                </div>
                <p className="text-base font-medium text-slate-500">Wallet Balance</p>
            </div>

            <p className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                {formatPrice(balance)}
            </p>
            {pendingBalance != null && pendingBalance > 0 && (
                <p className="mt-1 text-sm text-slate-500">
                    {formatPrice(pendingBalance)} clearing
                </p>
            )}

            <div className="mt-6 flex flex-wrap items-center gap-4">
                <Link
                    href={withdrawHref}
                    className="inline-flex items-center gap-2.5 rounded-full border border-sky-200 bg-white py-2 pl-2 pr-5 text-sm font-semibold text-sky-600 shadow-sm transition hover:border-sky-300 hover:bg-sky-50"
                >
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-500 text-white">
                        <ArrowDownToLine className="h-4 w-4" strokeWidth={2.25} />
                    </span>
                    Withdraw
                </Link>

                <Link
                    href={historyHref}
                    className="inline-flex items-center gap-2 text-sm font-medium text-slate-700 transition hover:text-sky-600"
                >
                    <History className="h-4 w-4" strokeWidth={1.75} />
                    History
                </Link>
            </div>
        </div>
    );
}
