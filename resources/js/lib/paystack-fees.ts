export type PaystackFeeTier = {
    min: number;
    max: number | null;
    fee: number;
};

export type PaystackFeeSettings = {
    enabled?: boolean;
    mode?: 'percent' | 'flat' | 'tiers';
    percent: number;
    flat: number;
    tiers?: PaystackFeeTier[];
};

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

function feeFromTiers(amount: number, tiers: PaystackFeeTier[] | undefined, fallback: number): number {
    if (!tiers?.length || amount <= 0) {
        return Math.max(0, fallback);
    }

    for (const tier of tiers) {
        const min = Number(tier.min) || 0;
        const max = tier.max == null ? null : Number(tier.max);
        if (amount + 0.0001 >= min && (max === null || amount <= max + 0.0001)) {
            return Number(tier.fee) || 0;
        }
    }

    if (amount < (Number(tiers[0].min) || 0)) {
        return Number(tiers[0].fee) || 0;
    }

    for (let i = 0; i < tiers.length - 1; i++) {
        const currMax = tiers[i].max;
        const nextMin = Number(tiers[i + 1].min) || 0;
        if (currMax != null && amount > Number(currMax) && amount < nextMin) {
            return Number(tiers[i + 1].fee) || 0;
        }
    }

    return Number(tiers[tiers.length - 1].fee) || 0;
}

/** Charge enough that the wallet / order still receives `credit` after the admin Paystack fee. */
export function paystackRechargeQuote(credit: number, fee?: PaystackFeeSettings | null) {
    const amount = Number.isFinite(credit) ? credit : 0;
    const enabled = fee?.enabled !== false;
    const mode = fee?.mode ?? 'percent';
    const percent = Math.max(0, Number(fee?.percent ?? 1.95));
    const flat = Math.max(0, Number(fee?.flat ?? 0));

    if (!amount || amount <= 0 || !enabled) {
        return { credit: round2(Math.max(0, amount)), fee: 0, charge: round2(Math.max(0, amount)), percent, flat, mode };
    }

    if (mode === 'tiers') {
        const band = round2(feeFromTiers(amount, fee?.tiers, flat));
        return {
            credit: round2(amount),
            fee: band,
            charge: round2(amount + band),
            percent: 0,
            flat: band,
            mode,
        };
    }

    if (mode === 'flat') {
        return {
            credit: round2(amount),
            fee: round2(flat),
            charge: round2(amount + flat),
            percent: 0,
            flat: round2(flat),
            mode,
        };
    }

    const rate = percent / 100;
    const charge = rate >= 1 ? round2(amount + flat) : round2((amount + flat) / (1 - rate));

    return {
        credit: round2(amount),
        fee: round2(charge - amount),
        charge,
        percent,
        flat,
        mode: 'percent' as const,
    };
}
