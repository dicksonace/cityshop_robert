import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { Paginated } from '@/types/marketplace';

type Quote = {
    rmb_amount: number;
    usd_per_rmb: number;
    ghs_per_usd: number;
    usd_gross: number;
    fee_usd: number;
    usd_payout: number;
    ghs_payout: number;
    payout_currency: string;
    payout_amount: number;
};

type Config = {
    enabled: boolean;
    instructions: string | null;
    rate: {
        usd_per_rmb: number;
        ghs_per_usd: number;
        min_rmb: number;
        max_rmb: number;
        fee_mode: 'flat' | 'percent';
        fee_value: number;
        updated_at: string | null;
    } | null;
    sample_quote: Quote | null;
};

type TransferRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: Quote;
    created_at: string | null;
};

interface Props {
    config: Config;
    transfers: Paginated<TransferRow>;
}

function formatUsd(n: number) {
    return `$${n.toFixed(2)}`;
}

function formatGhs(n: number) {
    return `GH₵${n.toFixed(2)}`;
}

export default function SellRmbHub({ config, transfers }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [rmb, setRmb] = useState(String(config.rate?.min_rmb ?? 1000));
    const [payoutCurrency, setPayoutCurrency] = useState<'usd' | 'ghs'>('ghs');

    const quote = useMemo(() => {
        const amount = Number(rmb);
        if (!config.rate || !Number.isFinite(amount) || amount <= 0) return null;
        const usdGross = amount * config.rate.usd_per_rmb;
        const fee =
            config.rate.fee_mode === 'percent'
                ? (usdGross * config.rate.fee_value) / 100
                : config.rate.fee_value;
        const usdPayout = usdGross - fee;
        const ghsPayout = usdPayout * config.rate.ghs_per_usd;
        return { usdGross, fee, usdPayout, ghsPayout };
    }, [rmb, config.rate]);

    return (
        <ShopLayout hideFlash>
            <Head title="Sell RMB" />
            {(flash.success || flash.error) && (
                <div
                    className={`mx-auto mt-4 max-w-lg rounded-xl px-4 py-3 text-sm font-medium ${
                        flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                    }`}
                >
                    {flash.success ?? flash.error}
                </div>
            )}
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.china-rmb.index')} className="text-sm font-semibold text-emerald-700">
                    ← China / RMB
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Sell RMB</h1>
                <p className="mt-1 text-sm text-gray-500">Send RMB · receive USD or GHS</p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 p-5 text-white shadow-lg">
                    <p className="text-sm font-semibold text-white/80">We buy your RMB</p>
                    <p className="mt-1 text-xs uppercase tracking-wide text-white/60">Buying rate</p>
                    {config.rate ? (
                        <>
                            <p className="mt-4 text-3xl font-black tracking-tight">
                                1 RMB = ${config.rate.usd_per_rmb.toFixed(4)}
                            </p>
                            <p className="mt-2 text-sm text-white/80">
                                1 USD = GH₵{config.rate.ghs_per_usd.toFixed(4)}
                            </p>
                        </>
                    ) : (
                        <p className="mt-4 text-lg font-semibold">Buying rate not published yet.</p>
                    )}
                </div>

                {config.instructions && (
                    <p className="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {config.instructions}
                    </p>
                )}

                {config.rate && (
                    <div className="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                        <label className="text-sm font-semibold text-gray-700">RMB amount to sell</label>
                        <Input
                            className="mt-2"
                            inputMode="decimal"
                            value={rmb}
                            onChange={(e) => setRmb(e.target.value)}
                        />
                        <p className="mt-4 text-sm font-semibold text-gray-700">Receive payout in</p>
                        <div className="mt-2 flex gap-2">
                            {(['ghs', 'usd'] as const).map((currency) => (
                                <button
                                    key={currency}
                                    type="button"
                                    onClick={() => setPayoutCurrency(currency)}
                                    className={`flex-1 rounded-xl border px-3 py-2 text-sm font-bold uppercase ${
                                        payoutCurrency === currency
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                                            : 'border-gray-200 text-gray-600'
                                    }`}
                                >
                                    {currency}
                                </button>
                            ))}
                        </div>
                        {quote && (
                            <dl className="mt-4 space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Gross (USD)</dt>
                                    <dd>{formatUsd(quote.usdGross)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Fee</dt>
                                    <dd>{formatUsd(quote.fee)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">USD payout</dt>
                                    <dd>{formatUsd(quote.usdPayout)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">GHS payout</dt>
                                    <dd>{formatGhs(quote.ghsPayout)}</dd>
                                </div>
                                <div className="flex justify-between border-t pt-2 font-bold">
                                    <dt>You receive</dt>
                                    <dd>
                                        {payoutCurrency === 'ghs'
                                            ? formatGhs(quote.ghsPayout)
                                            : formatUsd(quote.usdPayout)}
                                    </dd>
                                </div>
                            </dl>
                        )}
                        {config.enabled ? (
                            <Button
                                className="mt-4 w-full bg-emerald-600 hover:bg-emerald-700"
                                onClick={() =>
                                    router.get(route('wallet.sell-rmb.create'), {
                                        rmb_amount: rmb,
                                        payout_currency: payoutCurrency,
                                    })
                                }
                            >
                                Continue
                            </Button>
                        ) : (
                            <p className="mt-4 text-sm text-amber-700">
                                Sell RMB is paused until admin activates this service.
                            </p>
                        )}
                    </div>
                )}

                <h2 className="mt-8 text-lg font-bold text-gray-900">Your Sell RMB requests</h2>
                <div className="mt-3 space-y-3">
                    {transfers.data.length === 0 && (
                        <p className="text-sm text-gray-500">No Sell RMB requests yet.</p>
                    )}
                    {transfers.data.map((item) => {
                        const payout =
                            item.quote.payout_currency === 'ghs'
                                ? formatGhs(item.quote.ghs_payout)
                                : formatUsd(item.quote.usd_payout);
                        return (
                            <Link
                                key={item.id}
                                href={route('wallet.sell-rmb.show', item.id)}
                                className="block rounded-2xl border border-gray-200 bg-white p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-bold text-gray-900">{item.reference}</p>
                                        <p className="text-sm text-gray-500">
                                            ¥{item.quote.rmb_amount.toFixed(2)} → {payout}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        {item.status_label}
                                    </span>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </ShopLayout>
    );
}
