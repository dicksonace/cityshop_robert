import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { SellRmbStatusView, type SellRmbStatusTransfer } from '@/components/china/sell-rmb-status-view';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

interface Props {
    transfer: SellRmbStatusTransfer;
}

const TERMINAL = ['completed', 'cancelled', 'rejected', 'failed'];

export default function SellRmbShow({ transfer: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [transfer, setTransfer] = useState(initial);

    useEffect(() => {
        setTransfer(initial);
    }, [initial]);

    const autoRefresh = !TERMINAL.includes(transfer.status);

    const refreshTransfer = useCallback(async () => {
        const res = await fetch(`${route('wallet.sell-rmb.show', transfer.id)}?json=1`, {
            headers: { Accept: 'application/json' },
        });
        const body = await res.json();
        if (body?.data) {
            setTransfer(body.data);
        }
    }, [transfer.id]);

    useEffect(() => {
        if (!autoRefresh) return;
        const timer = window.setInterval(() => {
            void refreshTransfer();
        }, 8000);
        return () => window.clearInterval(timer);
    }, [transfer.id, transfer.status, autoRefresh, refreshTransfer]);

    return (
        <ShopLayout hideFlash>
            <Head title="Sell RMB Status" />
            <div className="min-h-[70vh] bg-gray-50 px-4 py-6">
                {(flash.success || flash.error) && (
                    <p
                        className={`mx-auto mb-4 max-w-md rounded-xl px-4 py-3 text-sm ${
                            flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </p>
                )}
                <SellRmbStatusView
                    transfer={transfer}
                    onRefresh={refreshTransfer}
                    walletHref={route('wallet.china-rmb.index')}
                    historyHref={route('wallet.china-rmb.index')}
                    onCancel={
                        transfer.can_cancel
                            ? () => {
                                  if (confirm('Cancel this Sell RMB request?')) {
                                      router.post(route('wallet.sell-rmb.cancel', transfer.id));
                                  }
                              }
                            : undefined
                    }
                />
            </div>
        </ShopLayout>
    );
}
