import { formatPrice } from '@/types/marketplace';

export type WithdrawalFeeSettings = {
    enabled: boolean;
    amount: number;
    momo_amount?: number;
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
        return 'Below GH₵1,000 → GH₵10 · From GH₵1,000 → GH₵20';
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

    const applies = payoutType === 'momo'
        ? (settings.momo_amount ?? 0) > 0 || fee > 0
        : settings.applies_to === 'all' || settings.applies_to === payoutType;
    if (!applies) {
        return null;
    }

    if (payoutType === 'bank') {
        const shown = amount > 0 ? fee : (settings.bank_tiers?.[0]?.fee ?? settings.amount);
        return (
            <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950">
                <p className="font-semibold">Bank withdrawal fee</p>
                <p className="mt-1 text-sm font-semibold">
                    {amount > 0 && fee > 0
                        ? `${formatPrice(fee)} for this amount`
                        : `${formatPrice(Number(shown) || 10)}+ depending on amount`}
                </p>
                <p className="mt-1 text-xs text-orange-900/80">
                    Fee is deducted from your wallet with the withdrawal — leave enough balance, or use Withdraw all.
                </p>
            </div>
        );
    }

    if (fee <= 0 && (settings.momo_amount ?? 0) <= 0 && (settings.amount ?? 0) <= 0) {
        return null;
    }

    const shown = amount > 0 ? fee : (settings.momo_amount ?? settings.amount);

    return (
        <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950">
            <p className="font-semibold">Mobile Money withdrawal fee</p>
            <p className="mt-1 text-sm font-semibold">{formatPrice(shown)} per withdrawal</p>
        </div>
    );
}
