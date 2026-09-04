import { Link } from '@inertiajs/react';
import { History, RefreshCw } from 'lucide-react';
import { useState } from 'react';

import RechargeModal from '@/components/wallet/recharge-modal';
import { type FundingAccount } from '@/components/wallet/manual-top-up-form';
import { type PaystackFeeSettings } from '@/lib/paystack-fees';
import { cn } from '@/lib/utils';
import { formatPrice } from '@/types/marketplace';

interface WalletBalanceCardProps {
    balance: number;
    pendingBalance?: number;
    withdrawHref: string;
    historyHref: string;
    className?: string;
    onRefresh?: () => void;
    refreshing?: boolean;
    countdownSec?: number;
    paystackConfigured?: boolean;
    flutterwaveConfigured?: boolean;
    manualTopUpEnabled?: boolean;
    manualFundingAccounts?: FundingAccount[];
    paystackFee?: PaystackFeeSettings | null;
}

export default function WalletBalanceCard({
    balance,
    pendingBalance,
    withdrawHref,
    historyHref,
    className,
    onRefresh,
    refreshing = false,
    countdownSec,
    paystackConfigured = false,
    flutterwaveConfigured = false,
    manualTopUpEnabled = false,
    manualFundingAccounts = [],
    paystackFee,
}: WalletBalanceCardProps) {
    const canRecharge = paystackConfigured || flutterwaveConfigured || manualTopUpEnabled;
    const [rechargeOpen, setRechargeOpen] = useState(false);

    return (
        <>
            <div
                className={cn(
                    'relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-orange-500 via-orange-600 to-orange-700 p-5 text-white shadow-lg sm:p-6',
                    className,
                )}
            >
                <div className="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10" />
                <div className="pointer-events-none absolute -bottom-12 -left-8 h-40 w-40 rounded-full bg-black/10" />

                <div className="relative flex items-start justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-orange-100">Available balance</p>
                        {countdownSec != null && (
                            <p className="mt-0.5 text-xs text-orange-100/80">Auto refresh in {countdownSec}s</p>
                        )}
                    </div>
                    {onRefresh && (
                        <button
                            type="button"
                            onClick={onRefresh}
                            disabled={refreshing}
                            className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/25 disabled:opacity-60"
                        >
                            <RefreshCw className={cn('h-3.5 w-3.5', refreshing && 'animate-spin')} />
                            Refresh
                        </button>
                    )}
                </div>

                <p className="relative mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    {formatPrice(balance)}
                </p>
                {pendingBalance != null && pendingBalance > 0 && (
                    <p className="relative mt-1.5 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                        {formatPrice(pendingBalance)} clearing
                    </p>
                )}

                <div className="relative mt-5 flex h-12 overflow-hidden rounded-full bg-white shadow-md">
                    <Link
                        href={withdrawHref}
                        className="flex flex-1 items-center justify-center text-sm font-extrabold text-slate-900 transition hover:bg-slate-50"
                    >
                        Withdrawal
                    </Link>
                    <button
                        type="button"
                        onClick={canRecharge ? () => setRechargeOpen(true) : undefined}
                        disabled={!canRecharge}
                        className={cn(
                            'flex flex-1 items-center justify-center rounded-full text-sm font-extrabold text-white transition',
                            canRecharge
                                ? 'bg-orange-500 hover:bg-orange-600'
                                : 'cursor-not-allowed bg-slate-400',
                        )}
                    >
                        {canRecharge ? 'Recharge' : 'Unavailable'}
                    </button>
                </div>

                <div className="relative mt-3 flex items-center justify-between gap-3">
                    <Link
                        href={historyHref}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-50 transition hover:text-white"
                    >
                        <History className="h-4 w-4" strokeWidth={1.75} />
                        History
                    </Link>
                    {!canRecharge && (
                        <p className="text-[11px] text-orange-100/90">Recharge unavailable right now</p>
                    )}
                </div>
            </div>

            <RechargeModal
                open={rechargeOpen}
                onClose={() => setRechargeOpen(false)}
                paystackConfigured={paystackConfigured}
                flutterwaveConfigured={flutterwaveConfigured}
                manualTopUpEnabled={manualTopUpEnabled}
                manualFundingAccounts={manualFundingAccounts}
                manualHref={route('seller.wallet.manual-top-up')}
                paystackRoute={route('seller.wallet.add-funds')}
                flutterwaveRoute={route('seller.wallet.add-funds.flutterwave')}
                amountInputId="seller-recharge-amount"
                paystackFee={paystackFee}
            />
        </>
    );
}
