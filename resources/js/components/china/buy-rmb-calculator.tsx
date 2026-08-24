import { useMemo, useState } from 'react';

import { cn } from '@/lib/utils';

type Rate = {
    ghs_per_rmb: number;
    rmb_per_ghs: number;
    fee_mode?: 'flat' | 'percent';
    fee_value?: number;
};

type Props = {
    rate: Rate;
    enabled: boolean;
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

/**
 * Buy-rmb.com-style calculator: Today's Rate, You send / They receive, arrival copy, Continue.
 */
export default function BuyRmbCalculator({ rate, enabled, initialGhs = '', onContinue, className }: Props) {
    const [ghs, setGhs] = useState(initialGhs);
    const [cny, setCny] = useState(() => {
        const amount = Number(initialGhs);
        if (!Number.isFinite(amount) || amount <= 0 || rate.ghs_per_rmb <= 0) return '';
        return formatAmount(amount / rate.ghs_per_rmb);
    });
    const [focus, setFocus] = useState<'ghs' | 'cny' | null>('ghs');

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

    return (
        <div className={cn('rounded-3xl border border-gray-200 bg-white p-5 shadow-sm', className)}>
            <div className="text-center">
                <p className="text-sm font-semibold text-gray-500">Today&apos;s Rate</p>
                <p className="mt-1 text-lg font-extrabold tracking-tight text-indigo-600">
                    1 GHS = {rate.rmb_per_ghs.toFixed(2)} CNY
                </p>
            </div>

            <label className="mt-6 block text-sm font-semibold text-gray-600">You send</label>
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

            <label className="mt-4 block text-sm font-semibold text-gray-600">They receive</label>
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

            {quote && quote.fee > 0 && (
                <p className="mt-3 text-center text-xs text-gray-500">
                    Fee GH₵{quote.fee.toFixed(2)} · Total GH₵{quote.total.toFixed(2)}
                </p>
            )}

            <p className="mt-4 text-center text-sm font-semibold text-emerald-600">
                Arrives in 5–30 minutes
            </p>

            <button
                type="button"
                disabled={!canContinue}
                onClick={() => canContinue && onContinue(String(quote!.send))}
                className="mt-5 w-full rounded-full bg-indigo-500 py-3.5 text-base font-extrabold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:bg-gray-300"
            >
                {enabled ? 'Continue' : 'Transfers paused'}
            </button>
        </div>
    );
}
