import type { WithdrawalFeeSettings } from '@/components/wallet/withdrawal-fee-notice';

type FeeTier = { min: number; max: number | null; fee: number; mode?: 'flat' | 'percent' };

function feeFromTiers(amount: number, tiers: FeeTier[], fallback = 0): number {
    if (amount <= 0 || tiers.length === 0) return Math.max(0, fallback);
    let tier: FeeTier | undefined;
    for (const row of tiers) {
        if (amount + 0.0001 >= row.min && (row.max == null || amount <= row.max + 0.0001)) {
            tier = row;
            break;
        }
    }
    if (!tier) {
        if (amount < tiers[0].min) tier = tiers[0];
        else {
            for (let i = 0; i < tiers.length - 1; i++) {
                const currMax = tiers[i].max;
                const nextMin = tiers[i + 1].min;
                if (currMax != null && amount > currMax && amount < nextMin) {
                    tier = tiers[i + 1];
                    break;
                }
            }
            tier = tier ?? tiers[tiers.length - 1];
        }
    }
    const fee = Number(tier.fee) || 0;
    if (tier.mode === 'percent') {
        return Math.round(amount * (fee / 100) * 100) / 100;
    }
    return fee;
}

function momoFeeAmount(settings: WithdrawalFeeSettings): number {
    if (settings.momo_amount != null && settings.momo_amount !== undefined) {
        return Number(settings.momo_amount) || 0;
    }
    if (settings.applies_to === 'momo' || settings.applies_to === 'all') {
        return Number(settings.amount) || 0;
    }
    return 0;
}

/** Match server PlatformSettings::feeForWithdrawal / feeForPayoutType. */
export function feeForPayoutType(
    settings: WithdrawalFeeSettings | undefined,
    payoutType: 'momo' | 'bank',
    amount = 0,
): number {
    if (!settings) return 0;
    const amt = Number(amount) || 0;
    const percent = Number(settings.percent) || 0;
    // Percent only when a % is set. Legacy auto-Paystack 0% payloads used mode=percent
    // with enabled=false and empty bank_tiers — fall through to flat/tier fees.
    if (settings.mode === 'percent' && percent > 0) {
        return Math.round(amt * (percent / 100) * 100) / 100;
    }
    if (settings.applies_to === 'none') return 0;
    if (payoutType === 'momo') {
        const tiers = settings.momo_tiers;
        if (tiers && tiers.length > 0) {
            return feeFromTiers(amt, tiers, 0);
        }
        const momoPercent = Number(settings.momo_percent) || 0;
        if (momoPercent > 0) {
            return Math.round(amt * (momoPercent / 100) * 100) / 100;
        }
        const momo = momoFeeAmount(settings);
        if (momo > 0) return momo;
        if (tiers == null) {
            return feeFromTiers(amt, [
                { min: 1, max: 49.99, fee: 0.5, mode: 'flat' },
                { min: 50, max: 1999.99, fee: 1, mode: 'percent' },
                { min: 2000, max: null, fee: 20, mode: 'flat' },
            ], 0);
        }
        return 0;
    }
    if (!(settings.applies_to === 'all' || settings.applies_to === 'bank')) return 0;
    const defaultTiers: FeeTier[] = [
        { min: 10, max: 999.99, fee: 10 },
        { min: 1000, max: 25000, fee: 20 },
    ];
    const tiers = (settings.bank_tiers?.length ?? 0) > 0 ? settings.bank_tiers! : defaultTiers;
    return feeFromTiers(amt, tiers, Number(settings.amount) || 0);
}

export function maxWithdrawableAmount(
    balance: number | string,
    settings: WithdrawalFeeSettings | undefined,
    payoutType: 'momo' | 'bank',
): number {
    const available = Number(balance) || 0;
    if (settings?.mode === 'percent' && (Number(settings.percent) || 0) > 0) {
        return Math.max(0, Math.floor((available / (1 + (Number(settings.percent) || 0) / 100)) * 100) / 100);
    }
    let lo = 0;
    let hi = available;
    for (let i = 0; i < 48; i++) {
        const mid = (lo + hi) / 2;
        const fee = feeForPayoutType(settings, payoutType, mid);
        if (mid + fee <= available + 1e-9) lo = mid;
        else hi = mid;
    }
    let amount = Math.round(lo * 100) / 100;
    if (amount + feeForPayoutType(settings, payoutType, amount) > available + 1e-9) {
        amount = Math.round((amount - 0.01) * 100) / 100;
    }
    return Math.max(0, amount);
}
