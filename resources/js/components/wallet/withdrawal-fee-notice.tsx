import { formatPrice } from '@/types/marketplace';

export type WithdrawalFeeSettings = {
    enabled: boolean;
    amount: number;
    percent?: number;
    mode?: 'flat' | 'percent';
    applies_to: 'bank' | 'momo' | 'all' | 'none';
    auto_paystack?: boolean;
    bank_tiers?: { min: number; max: number | null; fee: number }[];
};

function formatGhc(value: number): string {
    return `GH₵${Number(value).toLocaleString('en-GH', { maximumFractionDigits: 2 })}`;
}

export function bankFeeScheduleLabel(tiers?: { min: number; max: number | null; fee: number }[]): string {
    if (!tiers?.length) {
        return 'GH₵10–1,000 → GH₵10 · GH₵1,001–25,000 → GH₵20';
    }

    return tiers
        .map((tier) => {
            const from = formatGhc(tier.min);
            const range = tier.max == null ? `${from}+` : `${from}–${formatGhc(tier.max)}`;
            return `${range} → ${formatGhc(tier.fee)}`;
        })
        .join(' · ');
}

export default function WithdrawalFeeNotice({
    payoutType,
    fee,
    amount = 0,
    settings,
}: {
    payoutType: 'momo' | 'bank';
    fee: number;
    amount?: number;
    settings?: WithdrawalFeeSettings;
}) {
    if (!settings?.enabled) {
        return null;
    }

    if (settings.mode === 'percent') {
        if ((settings.percent ?? 0) <= 0) {
            return null;
        }

        return (
            <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950">
                <p className="font-semibold">Withdrawal fee</p>
                <p className="mt-1 text-xs text-orange-900/80">
                    {settings.percent}% on every withdrawal
                    {amount > 0 && fee > 0 ? ` · this request ${formatPrice(fee)}` : ''}
                </p>
            </div>
        );
    }

    const applies = settings.applies_to === 'all' || settings.applies_to === payoutType;
    if (!applies) {
        return null;
    }

    if (payoutType === 'bank') {
        return (
            <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950">
                <p className="font-semibold">Bank withdrawal fee</p>
                <p className="mt-1 text-xs text-orange-900/80">{bankFeeScheduleLabel(settings.bank_tiers)}</p>
                {amount > 0 && fee > 0 && (
                    <p className="mt-2 text-sm font-semibold">
                        This withdrawal: fee {formatPrice(fee)} · Total deducted {formatPrice(amount + fee)}
                    </p>
                )}
                {amount <= 0 && (
                    <p className="mt-2 text-sm font-semibold">From GH₵10, bank fee is GH₵10.</p>
                )}
            </div>
        );
    }

    if (fee <= 0 && (settings.amount ?? 0) <= 0) {
        return null;
    }

    const shown = amount > 0 ? fee : settings.amount;

    return (
        <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950">
            <p className="font-semibold">Mobile Money withdrawal fee</p>
            <p className="mt-1 text-sm font-semibold">{formatPrice(shown)} per withdrawal</p>
        </div>
    );
}
