import { Head, Link, usePage } from '@inertiajs/react';

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
                <p className="mt-1 text-sm text-gray-500">Buy RMB for Alipay, or sell RMB for USD / GHS.</p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-teal-800 to-teal-900 p-5 text-white shadow-lg">
                    <p className="text-sm font-semibold text-white/80">RMB Rates</p>
                    <p className="mt-3 text-sm font-bold leading-relaxed">
                        {buyRate
                            ? `Buy / Transfer · 1 RMB = GH₵${buyRate.ghs_per_rmb.toFixed(4)}`
                            : 'Buy RMB rate: not published'}
                    </p>
                    <p className="mt-2 text-sm font-bold leading-relaxed">
                        {sellRate
                            ? `Sell (we buy) · 1 RMB = $${sellRate.usd_per_rmb.toFixed(4)} · 1 USD = GH₵${sellRate.ghs_per_usd.toFixed(2)}`
                            : 'Sell RMB rate: not published'}
                    </p>
                </div>

                <div className="mt-5 space-y-3">
                    <Link
                        href={route('wallet.china-transfer.index')}
                        className="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-orange-300"
                    >
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-lg font-black text-orange-600">
                            ↓
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="flex items-center justify-between gap-2">
                                <span className="font-bold text-gray-900">Buy RMB</span>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                        buy.config.enabled
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700'
                                    }`}
                                >
                                    {buy.config.enabled ? 'Open' : 'Paused'}
                                </span>
                            </span>
                            <span className="mt-1 block text-sm text-gray-500">
                                Pay GHS · receive RMB on Alipay in China
                            </span>
                        </span>
                    </Link>

                    <Link
                        href={route('wallet.sell-rmb.index')}
                        className="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-emerald-300"
                    >
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-lg font-black text-emerald-700">
                            ↑
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="flex items-center justify-between gap-2">
                                <span className="font-bold text-gray-900">Sell RMB</span>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                        sell.config.enabled
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700'
                                    }`}
                                >
                                    {sell.config.enabled ? 'Open' : 'Paused'}
                                </span>
                            </span>
                            <span className="mt-1 block text-sm text-gray-500">
                                Sell your RMB · receive USD or GHS
                            </span>
                        </span>
                    </Link>
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
                                    <p className="font-bold text-gray-900">Buy · {item.reference}</p>
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
