import { useMemo, useState } from 'react';

import BuyRmbClosedBanner from '@/components/china/buy-rmb-closed-banner';
import { cn } from '@/lib/utils';

type Rate = {
    ghs_per_rmb: number;
    rmb_per_ghs: number;
    fee_mode?: 'flat' | 'percent';
    fee_value?: number;
};

type TransferHours = {
    configured?: boolean;
    is_open_now?: boolean;
    open_time_label?: string | null;
    close_time_label?: string | null;
    closed_message?: string | null;
};

type Props = {
    rate: Rate;
    enabled: boolean;
    transferHours?: TransferHours | null;
    instructions?: string | null;
    initialGhs?: string;
    onContinue: (ghsAmount: string) => void;
    className?: string;
};

function round2(n: number) {
    return Math.round(n * 100) / 100;
}

function formatAmount(n: number) {
    if (!Number.isFinite(n) || n <= 0) return '';
    return String(round2(n));
}

function formatRate(n: number, digits = 4) {
    if (!Number.isFinite(n) || n <= 0) return '—';
    return n.toFixed(digits).replace(/\.?0+$/, '') || '0';
}

function LiveStatusChip({ live, serviceEnabled }: { live: boolean; serviceEnabled: boolean }) {
    if (!serviceEnabled) {
        return (
            <span className="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-extrabold text-gray-700">
                <span className="inline-block h-2 w-2 rounded-full bg-gray-400" />
                Paused
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-extrabold',
                live ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900',
            )}
        >
            <span className={cn('inline-block h-2 w-2 rounded-full', live ? 'bg-emerald-500' : 'bg-amber-500')} />
            {live ? 'Live' : 'Paused'}
        </span>
    );
}

export default function BuyRmbCalculator({
    rate,
    enabled,
    transferHours,
    instructions,
    initialGhs = '',
    onContinue,
    className,
}: Props) {
    const [ghs, setGhs] = useState(initialGhs);
    const [cny, setCny] = useState(() => {
        const amount = Number(initialGhs);
        if (!Number.isFinite(amount) || amount <= 0 || rate.ghs_per_rmb <= 0) return '';
        return formatAmount(amount / rate.ghs_per_rmb);
    });
    const [focus, setFocus] = useState<'ghs' | 'cny' | null>('cny');

    const hoursOpen = !transferHours?.configured || transferHours.is_open_now !== false;
    const isLiveNow = enabled && hoursOpen;

    const quote = useMemo(() => {
        const send = Number(ghs);
        if (!Number.isFinite(send) || send <= 0 || rate.ghs_per_rmb <= 0) return null;
        const receive = round2(send / rate.ghs_per_rmb);
        const feeValue = rate.fee_value ?? 0;
        const fee =
            rate.fee_mode === 'percent' ? round2((send * feeValue) / 100) : round2(feeValue);
        return { send, receive, fee, total: round2(send + fee) };
    }, [ghs, rate]);

    const onGhsChange = (raw: string) => {
        const cleaned = raw.replace(/[^\d.]/g, '');
        setGhs(cleaned);
        const amount = Number(cleaned);
        if (!Number.isFinite(amount) || amount <= 0 || rate.ghs_per_rmb <= 0) {
            setCny('');
            return;
        }
        setCny(formatAmount(amount / rate.ghs_per_rmb));
    };

    const onCnyChange = (raw: string) => {
        const cleaned = raw.replace(/[^\d.]/g, '');
        setCny(cleaned);
        const amount = Number(cleaned);
        if (!Number.isFinite(amount) || amount <= 0 || rate.ghs_per_rmb <= 0) {
            setGhs('');
            return;
        }
        setGhs(formatAmount(amount * rate.ghs_per_rmb));
    };

    const canContinue = enabled && quote !== null && quote.send > 0;
    const continueLabel = !enabled ? 'Transfers paused' : !hoursOpen ? 'Continue anyway' : 'Continue';

    return (
        <div className={cn('rounded-3xl border border-gray-200 bg-white p-5 shadow-sm', className)}>
            <div className="rounded-2xl border border-violet-400/60 bg-violet-800 px-5 py-5 text-center text-white shadow-sm">
                <p className="text-sm font-semibold tracking-wide">GHS to RMB</p>
                <p className="mt-2 text-2xl font-black tracking-tight">
                    1 GHS → {formatRate(rate.rmb_per_ghs)} RMB
                </p>
            </div>

            <label className="mt-6 block text-sm font-semibold text-gray-600">They receive</label>
            <div
                className={cn(
                    'mt-2 flex items-center gap-2 rounded-2xl border-2 bg-gray-50 px-4 py-3 transition',
                    focus === 'cny' ? 'border-indigo-500 bg-white' : 'border-gray-200',
                )}
            >
                <span className="text-xl font-bold text-gray-400">¥</span>
                <input
                    inputMode="decimal"
                    value={cny}
                    onFocus={() => setFocus('cny')}
                    onBlur={() => setFocus(null)}
                    onChange={(e) => onCnyChange(e.target.value)}
                    placeholder="0.00"
                    className="min-w-0 flex-1 bg-transparent text-2xl font-bold text-gray-900 outline-none placeholder:text-gray-300"
                />
                <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-extrabold text-gray-600">
                    CNY
                </span>
            </div>

            <label className="mt-4 block text-sm font-semibold text-gray-600">You send</label>
            <div
                className={cn(
                    'mt-2 flex items-center gap-2 rounded-2xl border-2 bg-gray-50 px-4 py-3 transition',
                    focus === 'ghs' ? 'border-indigo-500 bg-white' : 'border-gray-200',
                )}
            >
                <span className="text-xl font-bold text-gray-400">₵</span>
                <input
                    inputMode="decimal"
                    value={ghs}
                    onFocus={() => setFocus('ghs')}
                    onBlur={() => setFocus(null)}
                    onChange={(e) => onGhsChange(e.target.value)}
                    placeholder="0.00"
                    className="min-w-0 flex-1 bg-transparent text-2xl font-bold text-gray-900 outline-none placeholder:text-gray-300"
                />
                <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-extrabold text-gray-600">
                    GHS
                </span>
            </div>

            {quote && quote.fee > 0 && (
                <p className="mt-3 text-center text-xs text-gray-500">
                    Fee GH₵{quote.fee.toFixed(2)} · Total GH₵{quote.total.toFixed(2)}
                </p>
            )}

            <p
                className={cn(
                    'mt-4 text-center text-sm font-semibold',
                    hoursOpen ? 'text-emerald-600' : 'text-amber-700',
                )}
            >
                {hoursOpen
                    ? 'Arrives in 5–30 minutes'
                    : 'Orders outside hours are queued for the next open window.'}
            </p>

            {instructions?.trim() && (
                <p className="mt-3 text-center text-xs leading-relaxed text-gray-500">{instructions.trim()}</p>
            )}

            <div className="mt-5 flex items-center justify-between gap-3">
                <LiveStatusChip live={isLiveNow} serviceEnabled={enabled} />
                {transferHours?.open_time_label && transferHours.close_time_label && (
                    <p className="text-right text-[11px] font-bold text-gray-500">
                        {transferHours.open_time_label} – {transferHours.close_time_label}
                    </p>
                )}
            </div>

            {enabled && !hoursOpen && <BuyRmbClosedBanner transferHours={transferHours} className="mt-3" />}

            {!enabled && (
                <p className="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm font-semibold leading-relaxed text-gray-600">
                    Buy RMB is temporarily paused by admin. You can still check the rate, but new transfers are not
                    accepted right now.
                </p>
            )}

            <button
                type="button"
                disabled={!canContinue}
                onClick={() => canContinue && onContinue(String(quote!.send))}
                className={cn(
                    'mt-4 w-full rounded-full py-3.5 text-base font-extrabold text-white transition disabled:cursor-not-allowed disabled:bg-gray-300',
                    isLiveNow ? 'bg-indigo-500 hover:bg-indigo-600' : 'bg-amber-500 hover:bg-amber-600',
                )}
            >
                {continueLabel}
            </button>

            {canContinue && !hoursOpen && (
                <p className="mt-2 text-center text-[11px] font-semibold text-gray-500">
                    You can still submit — we process when transfer hours reopen.
                </p>
            )}
        </div>
    );
}
