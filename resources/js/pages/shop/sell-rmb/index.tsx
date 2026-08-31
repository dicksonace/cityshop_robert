import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { RmbAutoRefreshChip } from '@/components/china/rmb-transfer-status-badge';
import { RmbTransferListItem } from '@/components/china/rmb-transfer-list-item';
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
        ghs_per_rmb?: number;
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

const TERMINAL = ['completed', 'cancelled', 'rejected', 'failed'];

export default function SellRmbHub({ config, transfers }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [rmb, setRmb] = useState(String(config.rate?.min_rmb ?? 1000));

    const quote = useMemo(() => {
        const amount = Number(rmb);
        if (!config.rate || !Number.isFinite(amount) || amount <= 0) return null;
        const ghsPerRmb = config.rate.ghs_per_rmb ?? config.rate.usd_per_rmb * config.rate.ghs_per_usd;
        const usdGross = amount * config.rate.usd_per_rmb;
        const fee =
            config.rate.fee_mode === 'percent'
                ? (usdGross * config.rate.fee_value) / 100
                : config.rate.fee_value;
        const ghsGross = amount * ghsPerRmb;
        const feeGhs = usdGross > 0 ? ghsGross * (fee / usdGross) : 0;
        const ghsPayout = ghsGross - feeGhs;
        const usdPayout = usdGross - fee;
        return { usdGross, fee, usdPayout, ghsPayout, ghsPerRmb };
    }, [rmb, config.rate]);

    useEffect(() => {
        const hasOpen = transfers.data.some((item) => !TERMINAL.includes(item.status));
        if (!hasOpen && transfers.data.length > 0) {
            return;
        }
        const id = window.setInterval(() => {
            router.reload({
                only: ['config', 'transfers'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 8000);
        return () => window.clearInterval(id);
    }, [transfers.data]);

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
                <div className="flex items-center justify-between gap-3">
                    <Link href={route('wallet.china-rmb.index')} className="text-sm font-semibold text-emerald-700">
                        ← China / RMB
                    </Link>
                    <RmbAutoRefreshChip />
                </div>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Sell RMB</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Send RMB to our Alipay QR, upload your screenshot — we pay GHS after verification.
                </p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 p-5 text-white shadow-lg">
                    <p className="text-sm font-semibold text-white/80">We buy your RMB</p>
                    <p className="mt-1 text-xs uppercase tracking-wide text-white/60">Send RMB · receive GHS</p>
                    {config.rate ? (
                        <p className="mt-4 text-3xl font-black tracking-tight">
                            1 RMB = GH₵
                            {(config.rate.ghs_per_rmb ?? config.rate.usd_per_rmb * config.rate.ghs_per_usd).toFixed(4)}
                        </p>
                    ) : (
                        <p className="mt-4 text-lg font-semibold">Buying rate not published yet.</p>
                    )}
                </div>

                {config.instructions && (
                    <p className="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{config.instructions}</p>
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
                        <p className="mt-2 text-xs text-gray-500">
                            Min ¥{config.rate.min_rmb.toFixed(0)} · Max ¥{config.rate.max_rmb.toFixed(0)}
                        </p>
                        <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                            <p className="text-sm font-bold text-emerald-800">You receive (GHS)</p>
                            <p className="mt-1 text-3xl font-black tracking-tight text-emerald-900">
                                {quote ? formatGhs(quote.ghsPayout) : 'GH₵0.00'}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">
                                {quote
                                    ? `Estimated MoMo payout for ¥${Number(rmb || 0).toFixed(2)}`
                                    : 'Enter RMB above to see your GHS payout'}
                            </p>
                        </div>
                        {config.enabled ? (
                            <Button
                                className="mt-4 w-full bg-emerald-600 hover:bg-emerald-700"
                                disabled={!quote}
                                onClick={() =>
                                    router.get(route('wallet.sell-rmb.create'), {
                                        rmb_amount: rmb,
                                        payout_currency: 'ghs',
                                    })
                                }
                            >
                                Continue
                            </Button>
                        ) : (
                            <p className="mt-4 text-sm text-amber-700">Sell RMB is paused until admin activates this service.</p>
                        )}
                    </div>
                )}

                <div className="mt-8 flex items-center justify-between gap-3">
                    <h2 className="text-lg font-bold text-gray-900">Your Sell RMB requests</h2>
                    {transfers.data.length > 0 && (
                        <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                            {transfers.data.length}
                        </span>
                    )}
                </div>
                <div className="mt-3 space-y-3">
                    {transfers.data.length === 0 && (
                        <p className="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
                            No Sell RMB requests yet.
                        </p>
                    )}
                    {transfers.data.map((item) => {
                        const payout =
                            item.quote.payout_currency === 'ghs'
                                ? formatGhs(item.quote.ghs_payout)
                                : formatUsd(item.quote.usd_payout);
                        return (
                            <RmbTransferListItem
                                key={item.id}
                                href={route('wallet.sell-rmb.show', item.id)}
                                reference={item.reference}
                                subtitle={`¥${item.quote.rmb_amount.toFixed(2)} → ${payout}`}
                                status={item.status}
                                statusLabel={item.status_label}
                                sellFlow
                            />
                        );
                    })}
                </div>
            </div>
        </ShopLayout>
    );
}
