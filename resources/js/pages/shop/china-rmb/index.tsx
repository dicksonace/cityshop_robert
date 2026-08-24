import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type BuyRate = {
    ghs_per_rmb: number;
    rmb_per_ghs: number;
} | null;

type SellRate = {
    usd_per_rmb: number;
    ghs_per_usd: number;
    ghs_per_rmb?: number;
} | null;

type BuyTransfer = {
    id: number;
    reference: string;
    status_label: string;
    quote: { rmb_amount: number; total_payable_ghs: number };
};

type SellTransfer = {
    id: number;
    reference: string;
    status_label: string;
    quote: {
        rmb_amount: number;
        usd_payout: number;
        ghs_payout: number;
        payout_currency: string;
    };
};

interface Props {
    buy: {
        config: { enabled: boolean; rate: BuyRate; instructions: string | null };
        transfers: BuyTransfer[];
    };
    sell: {
        config: { enabled: boolean; rate: SellRate; instructions: string | null };
        transfers: SellTransfer[];
    };
}

function sellPayoutLabel(item: SellTransfer): string {
    return item.quote.payout_currency === 'ghs'
        ? `GH₵${item.quote.ghs_payout.toFixed(2)}`
        : `$${item.quote.usd_payout.toFixed(2)}`;
}

export default function ChinaRmbHub({ buy, sell }: Props) {
    const { flash } = usePage<SharedData>().props;
    const buyRate = buy.config.rate;
    const sellRate = sell.config.rate;
    const [exchangeType, setExchangeType] = useState<'buy' | 'sell'>('buy');

    const continueExchange = () => {
        if (exchangeType === 'buy') {
            if (!buy.config.enabled) {
                return;
            }
            router.visit(route('wallet.china-transfer.index'));
            return;
        }
        if (!sell.config.enabled) {
            return;
        }
        router.visit(route('wallet.sell-rmb.index'));
    };

    return (
        <ShopLayout hideFlash>
            <Head title="China / RMB" />
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
                <h1 className="mt-3 text-2xl font-black text-gray-900">China / RMB</h1>
                <p className="mt-1 text-sm text-gray-500">Choose your exchange direction below.</p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-teal-800 to-teal-900 p-5 text-white shadow-lg">
                    <p className="text-sm font-semibold text-white/80">RMB Rates</p>
                    <p className="mt-3 text-sm font-bold leading-relaxed">
                        {buyRate
                            ? `GHS → RMB · 1 GHS = ¥${buyRate.rmb_per_ghs.toFixed(3)} RMB`
                            : 'GHS → RMB: not published'}
                    </p>
                    <p className="mt-2 text-sm font-bold leading-relaxed">
                        {sellRate
                            ? `RMB → GHS · 1 RMB = GH₵${(sellRate.ghs_per_rmb ?? sellRate.usd_per_rmb * sellRate.ghs_per_usd).toFixed(4)}`
                            : 'RMB → GHS: not published'}
                    </p>
                </div>

                <div className="mt-6">
                    <p className="text-sm font-bold text-gray-700">Exchange Type</p>
                    <div className="mt-3 grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            onClick={() => setExchangeType('buy')}
                            className={`rounded-xl border-2 px-3 py-4 text-center transition ${
                                exchangeType === 'buy'
                                    ? 'border-emerald-600 bg-white'
                                    : 'border-gray-200 bg-white'
                            }`}
                        >
                            <p
                                className={`text-sm font-extrabold ${
                                    exchangeType === 'buy' ? 'text-emerald-700' : 'text-gray-700'
                                }`}
                            >
                                GHS → RMB
                            </p>
                            <p
                                className={`mt-1 text-xs font-semibold ${
                                    exchangeType === 'buy' ? 'text-emerald-500' : 'text-gray-400'
                                }`}
                            >
                                Ghana to China
                            </p>
                            {!buy.config.enabled ? (
                                <p className="mt-2 text-[10px] font-bold text-amber-700">Paused</p>
                            ) : (
                                <p className="mt-2 text-[10px] font-bold text-emerald-700">Live</p>
                            )}
                        </button>
                        <button
                            type="button"
                            onClick={() => setExchangeType('sell')}
                            className={`rounded-xl border-2 px-3 py-4 text-center transition ${
                                exchangeType === 'sell'
                                    ? 'border-emerald-600 bg-white'
                                    : 'border-gray-200 bg-white'
                            }`}
                        >
                            <p
                                className={`text-sm font-extrabold ${
                                    exchangeType === 'sell' ? 'text-emerald-700' : 'text-gray-700'
                                }`}
                            >
                                RMB → GHS
                            </p>
                            <p
                                className={`mt-1 text-xs font-semibold ${
                                    exchangeType === 'sell' ? 'text-emerald-500' : 'text-gray-400'
                                }`}
                            >
                                China to Ghana
                            </p>
                            {!sell.config.enabled ? (
                                <p className="mt-2 text-[10px] font-bold text-amber-700">Paused</p>
                            ) : (
                                <p className="mt-2 text-[10px] font-bold text-emerald-700">Live</p>
                            )}
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={continueExchange}
                        disabled={exchangeType === 'buy' ? !buy.config.enabled : !sell.config.enabled}
                        className="mt-4 w-full rounded-xl bg-teal-700 py-3 text-sm font-extrabold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-gray-400"
                    >
                        {exchangeType === 'buy'
                            ? buy.config.enabled
                                ? 'Continue · Buy RMB'
                                : 'GHS → RMB is paused'
                            : sell.config.enabled
                              ? 'Continue · Sell RMB'
                              : 'RMB → GHS is paused'}
                    </button>
                </div>

                <h2 className="mt-8 text-lg font-bold text-gray-900">Recent activity</h2>
                <div className="mt-3 space-y-3">
                    {buy.transfers.length === 0 && sell.transfers.length === 0 && (
                        <p className="text-sm text-gray-500">No China / RMB transactions yet.</p>
                    )}
                    {buy.transfers.map((item) => (
                        <Link
                            key={`buy-${item.id}`}
                            href={route('wallet.china-transfer.show', item.id)}
                            className="block rounded-2xl border border-gray-200 bg-white p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-bold text-gray-900">GHS → RMB · {item.reference}</p>
                                    <p className="text-sm text-gray-500">
                                        {formatPrice(item.quote.total_payable_ghs)} → ¥
                                        {item.quote.rmb_amount.toFixed(2)}
                                    </p>
                                </div>
                                <span className="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">
                                    {item.status_label}
                                </span>
                            </div>
                        </Link>
                    ))}
                    {sell.transfers.map((item) => (
                        <Link
                            key={`sell-${item.id}`}
                            href={route('wallet.sell-rmb.show', item.id)}
                            className="block rounded-2xl border border-gray-200 bg-white p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-bold text-gray-900">RMB → GHS · {item.reference}</p>
                                    <p className="text-sm text-gray-500">
                                        ¥{item.quote.rmb_amount.toFixed(2)} → {sellPayoutLabel(item)}
                                    </p>
                                </div>
                                <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
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
