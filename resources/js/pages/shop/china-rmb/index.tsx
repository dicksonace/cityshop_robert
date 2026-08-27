import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice, Wallet } from '@/types/marketplace';

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
    funding_source?: string;
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
    wallet: Wallet;
    buy: {
        config: {
            enabled: boolean;
            wallet_funding_enabled?: boolean;
            external_enabled?: boolean;
            rate: BuyRate;
            instructions: string | null;
        };
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

export default function ChinaRmbHub({ wallet, buy, sell }: Props) {
    const { flash } = usePage<SharedData>().props;
    const buyRate = buy.config.rate;
    const sellRate = sell.config.rate;
    const [exchangeType, setExchangeType] = useState<'buy' | 'sell' | 'convert'>('convert');

    const continueExchange = () => {
        if (exchangeType === 'convert') {
            router.visit(route('wallet.convert'));
            return;
        }
        if (exchangeType === 'buy') {
            if (!buy.config.enabled) {
                return;
            }
            router.visit(route('wallet.china-transfer.create'));
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
                <p className="mt-1 text-sm text-gray-500">
                    Convert in-wallet, transfer RMB to Alipay, or sell RMB for GHS.
                </p>

                <div className="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-gradient-to-br from-teal-800 to-teal-900 p-5 text-white shadow-lg">
                    <div>
                        <p className="text-xs font-semibold text-white/70">GHS wallet</p>
                        <p className="mt-1 text-xl font-black">{formatPrice(wallet.available_balance)}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-white/70">RMB wallet</p>
                        <p className="mt-1 text-xl font-black">¥{Number(wallet.rmb_balance ?? 0).toFixed(2)}</p>
                    </div>
                    <div className="col-span-2 border-t border-white/15 pt-3 text-sm font-bold leading-relaxed">
                        {buyRate
                            ? `Buy / convert · 1 GHS = ¥${buyRate.rmb_per_ghs.toFixed(3)}`
                            : 'GHS → RMB rate: not published'}
                        <br />
                        {sellRate
                            ? `Sell / convert · 1 RMB = GH₵${(sellRate.ghs_per_rmb ?? sellRate.usd_per_rmb * sellRate.ghs_per_usd).toFixed(4)}`
                            : 'RMB → GHS rate: not published'}
                    </div>
                </div>

                <div className="mt-6">
                    <p className="text-sm font-bold text-gray-700">What do you want to do?</p>
                    <div className="mt-3 grid grid-cols-1 gap-2.5">
                        <button
                            type="button"
                            onClick={() => setExchangeType('convert')}
                            className={`rounded-xl border-2 px-3 py-4 text-left transition ${
                                exchangeType === 'convert' ? 'border-emerald-600 bg-white' : 'border-gray-200 bg-white'
                            }`}
                        >
                            <p className="text-sm font-extrabold text-gray-900">Convert GHS ↔ RMB</p>
                            <p className="mt-1 text-xs font-semibold text-gray-500">Instant wallet exchange</p>
                        </button>
                        <button
                            type="button"
                            onClick={() => setExchangeType('buy')}
                            className={`rounded-xl border-2 px-3 py-4 text-left transition ${
                                exchangeType === 'buy' ? 'border-emerald-600 bg-white' : 'border-gray-200 bg-white'
                            }`}
                        >
                            <p className="text-sm font-extrabold text-gray-900">Transfer RMB to Alipay</p>
                            <p className="mt-1 text-xs font-semibold text-gray-500">
                                Send from RMB balance (or pay GHS externally)
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
                            className={`rounded-xl border-2 px-3 py-4 text-left transition ${
                                exchangeType === 'sell' ? 'border-emerald-600 bg-white' : 'border-gray-200 bg-white'
                            }`}
                        >
                            <p className="text-sm font-extrabold text-gray-900">Sell RMB → GHS</p>
                            <p className="mt-1 text-xs font-semibold text-gray-500">
                                Send RMB to CityShop Alipay, get MoMo payout
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
                        disabled={
                            exchangeType === 'buy'
                                ? !buy.config.enabled
                                : exchangeType === 'sell'
                                  ? !sell.config.enabled
                                  : false
                        }
                        className="mt-4 w-full rounded-xl bg-teal-700 py-3 text-sm font-extrabold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-gray-400"
                    >
                        {exchangeType === 'convert'
                            ? 'Continue · Convert'
                            : exchangeType === 'buy'
                              ? buy.config.enabled
                                  ? 'Continue · Transfer to Alipay'
                                  : 'Transfers paused'
                              : sell.config.enabled
                                ? 'Continue · Sell RMB'
                                : 'Sell RMB is paused'}
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
                                    <p className="font-bold text-gray-900">
                                        Alipay · {item.reference}
                                        {item.funding_source === 'rmb_wallet' ? ' · Wallet' : ''}
                                    </p>
                                    <p className="text-sm text-gray-500">
                                        {item.funding_source === 'rmb_wallet'
                                            ? `¥${item.quote.rmb_amount.toFixed(2)} from wallet`
                                            : `${formatPrice(item.quote.total_payable_ghs)} → ¥${item.quote.rmb_amount.toFixed(2)}`}
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
                                    <p className="font-bold text-gray-900">Sell · {item.reference}</p>
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
