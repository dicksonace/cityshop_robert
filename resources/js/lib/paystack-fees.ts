export type PaystackFeeSettings = {
    percent: number;
    flat: number;
};

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

/** Charge enough that after Paystack's cut, the wallet still receives `credit`. */
export function paystackRechargeQuote(credit: number, fee?: PaystackFeeSettings | null) {
    const percent = Math.max(0, Number(fee?.percent ?? 1.95));
    const flat = Math.max(0, Number(fee?.flat ?? 0));
    const amount = Number.isFinite(credit) ? credit : 0;
    const rate = percent / 100;

    if (!amount || amount <= 0) {
        return { credit: 0, fee: 0, charge: 0, percent, flat };
    }

    const charge = rate >= 1 ? round2(amount + flat) : round2((amount + flat) / (1 - rate));

    return {
        credit: round2(amount),
        fee: round2(charge - amount),
        charge,
        percent,
        flat,
    };
}
