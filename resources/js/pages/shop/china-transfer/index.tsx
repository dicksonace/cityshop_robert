import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice, Paginated } from '@/types/marketplace';

type Quote = {
    ghs_amount: number;
    ghs_per_rmb: number;
    rmb_per_ghs: number;
    rmb_amount: number;
    fee_ghs: number;
    total_payable_ghs: number;
    breakdown: Record<string, string>;
};

type Config = {
    enabled: boolean;
    channel_label: string;
    instructions: string | null;
    rate: {
        ghs_per_rmb: number;
        rmb_per_ghs: number;
        min_ghs: number;
        max_ghs: number;
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

export default function ChinaTransferHub({ config, transfers }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [ghs, setGhs] = useState(String(config.rate?.min_ghs ?? 1000));
    const quote = useMemo(() => {
        const amount = Number(ghs);
        if (!config.rate || !Number.isFinite(amount) || amount <= 0) return null;
        const rmb = amount / config.rate.ghs_per_rmb;
        const fee =
            config.rate.fee_mode === 'percent'
                ? (amount * config.rate.fee_value) / 100
                : config.rate.fee_value;
        return { rmb, fee, total: amount + fee };
    }, [ghs, config.rate]);

    return (
        <ShopLayout hideFlash>
            <Head title="Transfer to China" />
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
                <Link href={route('wallet.index')} className="text-sm font-semibold text-orange-600">
                    ← Wallet
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Transfer to China</h1>
                <p className="mt-1 text-sm text-gray-500">GHS → RMB via Alipay. WeChat Pay is not available.</p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-violet-800 to-purple-700 p-5 text-white shadow-lg">
                    <p className="text-sm font-semibold text-white/80">Current exchange rates</p>
                    <p className="mt-1 text-xs uppercase tracking-wide text-white/60">GHS to RMB · Alipay</p>
                    {config.rate ? (
                        <>
                            <p className="mt-4 text-3xl font-black tracking-tight">
                                1 GHS → {config.rate.rmb_per_ghs.toFixed(3)} RMB
                            </p>
                            <p className="mt-2 text-sm text-white/80">
                                1 RMB = GH₵{config.rate.ghs_per_rmb.toFixed(4)}
                            </p>
                        </>
                    ) : (
                        <p className="mt-4 text-lg font-semibold">Rate not published yet.</p>
                    )}
                </div>

                {config.instructions && (
                    <p className="mt-4 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">{config.instructions}</p>
                )}

                {config.rate && (
                    <div className="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                        <label className="text-sm font-semibold text-gray-700">Amount to send (GHS)</label>
                        <Input className="mt-2" inputMode="decimal" value={ghs} onChange={(e) => setGhs(e.target.value)} />
                        {quote && (
                            <dl className="mt-4 space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">RMB value</dt>
                                    <dd className="font-semibold">¥{quote.rmb.toFixed(2)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Transfer fee</dt>
                                    <dd>GH₵{quote.fee.toFixed(2)}</dd>
                                </div>
                                <div className="flex justify-between border-t pt-2 font-bold">
                                    <dt>Total payment</dt>
                                    <dd>{formatPrice(quote.total)}</dd>
                                </div>
                            </dl>
                        )}
                        {config.enabled ? (
                            <Button
                                className="mt-4 w-full bg-orange-500 hover:bg-orange-600"
                                onClick={() =>
                                    router.get(route('wallet.china-transfer.create'), { ghs_amount: ghs })
                                }
                            >
                                Continue to Alipay details
                            </Button>
                        ) : (
                            <p className="mt-4 text-sm text-amber-700">Transfers are paused until admin activates this service.</p>
                        )}
                    </div>
                )}

                <h2 className="mt-8 text-lg font-bold text-gray-900">Your transfers</h2>
                <div className="mt-3 space-y-3">
                    {transfers.data.length === 0 && <p className="text-sm text-gray-500">No China transfers yet.</p>}
                    {transfers.data.map((item) => (
                        <Link
                            key={item.id}
                            href={route('wallet.china-transfer.show', item.id)}
                            className="block rounded-2xl border border-gray-200 bg-white p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-bold text-gray-900">{item.reference}</p>
                                    <p className="text-sm text-gray-500">
                                        {formatPrice(item.quote.total_payable_ghs)} → ¥{item.quote.rmb_amount.toFixed(2)}
                                    </p>
                                </div>
                                <span className="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">
                                    {item.status_label}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </ShopLayout>
    );
}
