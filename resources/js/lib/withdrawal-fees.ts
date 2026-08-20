import type { WithdrawalFeeSettings } from '@/components/wallet/withdrawal-fee-notice';

type BankFeeTiers = NonNullable<WithdrawalFeeSettings['bank_tiers']>;

function feeFromBankTiers(amount: number, tiers: BankFeeTiers, fallback = 0): number {
    if (amount <= 0 || tiers.length === 0) return Math.max(0, fallback);
    for (const tier of tiers) {
        if (amount + 0.0001 >= tier.min && (tier.max == null || amount <= tier.max + 0.0001)) {
            return Number(tier.fee) || 0;
        }
    }
    if (amount < tiers[0].min) return Number(tiers[0].fee) || 0;
    for (let i = 0; i < tiers.length - 1; i++) {
        const currMax = tiers[i].max;
        const nextMin = tiers[i + 1].min;
        if (currMax != null && amount > currMax && amount < nextMin) {
            return Number(tiers[i + 1].fee) || 0;
        }
    }
    return Number(tiers[tiers.length - 1].fee) || 0;
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
    if (!settings.enabled && settings.mode !== 'percent') return 0;
    if (settings.applies_to === 'none') return 0;
    if (payoutType === 'momo') {
        const momo = momoFeeAmount(settings);
        return momo > 0 ? momo : 0;
    }
    if (!(settings.applies_to === 'all' || settings.applies_to === 'bank')) return 0;
    const defaultTiers: BankFeeTiers = [
        { min: 10, max: 999.99, fee: 10 },
        { min: 1000, max: 25000, fee: 20 },
    ];
    const tiers = (settings.bank_tiers?.length ?? 0) > 0 ? settings.bank_tiers! : defaultTiers;
    return feeFromBankTiers(amt, tiers, Number(settings.amount) || 0);
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
