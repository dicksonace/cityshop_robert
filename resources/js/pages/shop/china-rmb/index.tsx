import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import { RmbAutoRefreshChip } from '@/components/china/rmb-transfer-status-badge';
import { RmbTransferListItem } from '@/components/china/rmb-transfer-list-item';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice, Wallet } from '@/types/marketplace';

type BuyRate = {
    ghs_per_rmb: number;
    rmb_per_ghs: number;
} | null;

type BuyConfig = {
    enabled: boolean;
    rate: BuyRate;
    instructions: string | null;
    transfer_hours?: {
        configured?: boolean;
        in_processing_window?: boolean;
        open_time_label?: string | null;
        close_time_label?: string | null;
        processing_note?: string | null;
    };
};

type SellRate = {
    usd_per_rmb: number;
    ghs_per_usd: number;
    ghs_per_rmb?: number;
} | null;

type BuyTransfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: { rmb_amount: number; total_payable_ghs: number };
};

type SellTransfer = {
    id: number;
    reference: string;
    status: string;
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
        config: BuyConfig;
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

function sellGhsPerRmb(rate: SellRate): number | null {
    if (!rate) {
        return null;
    }

    const fromDirect = Number(rate.ghs_per_rmb);
    const fromUsd = Number(rate.usd_per_rmb) * Number(rate.ghs_per_usd);
    const value = fromDirect > 0 ? fromDirect : fromUsd;

    return Number.isFinite(value) && value > 0 ? value : null;
}

/** China / RMB entry: Buy RMB (pay GHS → Alipay) or Sell RMB. No convert / no hold. */
export default function ChinaRmbHub({ buy, sell }: Props) {
    const { flash } = usePage<SharedData>().props;
    const buyRate = buy.config.rate;
    const sellRateValue = sellGhsPerRmb(sell.config.rate);
    const buyHours = buy.config.transfer_hours;
    const sellOpen = sell.config.enabled && sellRateValue !== null;
    const buyProcessingNote = buy.config.enabled && buyRate && buyHours?.in_processing_window === false
        ? 'Transfer now will be processed tomorrow morning by 7:00 AM.'
        : null;

    useEffect(() => {
        const id = window.setInterval(() => {
            router.reload({
                only: ['buy', 'sell', 'wallet'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 8000);
        return () => window.clearInterval(id);
    }, []);

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
                    Buy RMB for Alipay at today’s rate, or sell RMB for MoMo. No RMB is held in your wallet.
                </p>

                <div className="mt-5 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 p-5 text-white shadow-lg">
                    <p className="text-xs font-semibold uppercase tracking-wide text-white/70">Buy RMB</p>
                    <p className="mt-2 text-2xl font-black">
                        {buyRate ? `1 GHS → ${buyRate.rmb_per_ghs.toFixed(3)} RMB` : 'Rate not published'}
                    </p>
                    <p className="mt-1 text-sm text-white/80">No hidden fees · Secure transactions</p>
                    {buy.config.enabled && buyRate && buyProcessingNote && (
                        <p className="mt-3 rounded-xl border border-white/25 bg-white/15 px-3 py-2.5 text-sm font-semibold leading-snug text-white">
                            {buyProcessingNote}
                        </p>
                    )}
                    <button
                        type="button"
                        disabled={!buy.config.enabled || !buyRate}
                        onClick={() => router.visit(route('wallet.china-transfer.index'))}
                        className="mt-4 w-full rounded-xl bg-white py-3 text-sm font-extrabold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:bg-white/40 disabled:text-white"
                    >
                        {!buy.config.enabled ? 'Buy RMB paused' : 'Buy RMB →'}
                    </button>
                </div>

                <div className="mt-3 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-500 p-5 text-white shadow-lg">
                    <p className="text-xs font-semibold uppercase tracking-wide text-white/70">Sell RMB</p>
                    <p className="mt-2 text-2xl font-black">
                        {sellRateValue ? `1 RMB → GH₵${sellRateValue.toFixed(4)}` : 'Rate not published'}
                    </p>
                    <p className="mt-1 text-sm text-white/80">Send RMB via Alipay · get MoMo payout</p>
                    {!sell.config.enabled && (
                        <p className="mt-3 rounded-xl border border-white/25 bg-white/15 px-3 py-2.5 text-sm font-semibold leading-snug text-white">
                            Sell RMB is paused
                        </p>
                    )}
                    <button
                        type="button"
                        disabled={!sellOpen}
                        onClick={() => router.visit(route('wallet.sell-rmb.index'))}
                        className="mt-4 w-full rounded-xl bg-white py-3 text-sm font-extrabold text-emerald-800 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:bg-white/40 disabled:text-white"
                    >
                        {!sell.config.enabled ? 'Sell RMB paused' : sellRateValue ? 'Sell RMB →' : 'Rate not published'}
                    </button>
                </div>

                <div className="mt-8 flex items-center justify-between gap-3">
                    <h2 className="text-lg font-bold text-gray-900">Recent activity</h2>
                    <RmbAutoRefreshChip />
                </div>
                <div className="mt-3 space-y-3">
                    {buy.transfers.length === 0 && sell.transfers.length === 0 && (
                        <p className="text-sm text-gray-500">No China / RMB transactions yet.</p>
                    )}
                    {buy.transfers.map((item) => (
                        <RmbTransferListItem
                            key={`buy-${item.id}`}
                            href={route('wallet.china-transfer.show', item.id)}
                            reference={`Buy RMB · ${item.reference}`}
                            subtitle={`${formatPrice(item.quote.total_payable_ghs)} → ¥${item.quote.rmb_amount.toFixed(2)}`}
                            status={item.status}
                            statusLabel={item.status_label}
                        />
                    ))}
                    {sell.transfers.map((item) => (
                        <RmbTransferListItem
                            key={`sell-${item.id}`}
                            href={route('wallet.sell-rmb.show', item.id)}
                            reference={`Sell · ${item.reference}`}
                            subtitle={`¥${item.quote.rmb_amount.toFixed(2)} → ${sellPayoutLabel(item)}`}
                            status={item.status}
                            statusLabel={item.status_label}
                            sellFlow
                        />
                    ))}
                </div>
            </div>
        </ShopLayout>
    );
}
