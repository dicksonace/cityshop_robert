import { Head, Link, router, usePage } from '@inertiajs/react';

import BuyRmbCalculator from '@/components/china/buy-rmb-calculator';
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

    return (
        <ShopLayout hideFlash>
            <Head title="Buy RMB" />
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
                <Link href={route('wallet.china-rmb.index')} className="text-sm font-semibold text-orange-600">
                    ← China / RMB
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Buy RMB</h1>
                <p className="mt-1 text-sm text-gray-500">Send GHS, receive CNY in China via Alipay.</p>

                {config.instructions && (
                    <p className="mt-4 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">{config.instructions}</p>
                )}

                {config.rate ? (
                    <BuyRmbCalculator
                        className="mt-5"
                        rate={config.rate}
                        enabled={config.enabled}
                        initialGhs={String(config.rate.min_ghs || '')}
                        onContinue={(ghsAmount) =>
                            router.get(route('wallet.china-transfer.create'), { ghs_amount: ghsAmount })
                        }
                    />
                ) : (
                    <div className="mt-5 rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-center">
                        <p className="font-semibold text-gray-700">Rate not published yet</p>
                        <p className="mt-2 text-sm text-gray-500">
                            China transfers will open here once admin publishes a rate.
                        </p>
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
