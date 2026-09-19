import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { WithdrawalStatusView, type WithdrawalStatusViewData } from '@/components/wallet/withdrawal-status-view';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

interface Props {
    withdrawal: WithdrawalStatusViewData;
}

const TERMINAL = ['paid', 'rejected'];

export default function BuyerWithdrawalShow({ withdrawal: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [withdrawal, setWithdrawal] = useState(initial);

    useEffect(() => {
        setWithdrawal(initial);
    }, [initial]);

    const autoRefresh = !TERMINAL.includes(withdrawal.status);

    const refreshWithdrawal = useCallback(async () => {
        const res = await fetch(`${route('wallet.withdrawals.show', withdrawal.id)}?json=1`, {
            headers: { Accept: 'application/json' },
        });
        const body = await res.json();
        if (body?.data) {
            setWithdrawal(body.data);
        }
    }, [withdrawal.id]);

    useEffect(() => {
        if (!autoRefresh) return;
        const timer = window.setInterval(() => {
            void refreshWithdrawal();
        }, 8000);
        return () => window.clearInterval(timer);
    }, [withdrawal.id, withdrawal.status, autoRefresh, refreshWithdrawal]);

    return (
        <ShopLayout hideFlash>
            <Head title="Withdrawal status" />
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
                <WithdrawalStatusView
                    withdrawal={withdrawal}
                    onRefresh={refreshWithdrawal}
                    walletHref={route('wallet.index')}
                    historyHref={route('wallet.index')}
                />
            </div>
        </ShopLayout>
    );
}
