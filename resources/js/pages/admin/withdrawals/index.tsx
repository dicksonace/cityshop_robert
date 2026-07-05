import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated, Withdrawal } from '@/types/marketplace';

interface WithdrawalsIndexProps {
    withdrawals: Paginated<Withdrawal & { user: { name: string; email: string; role?: string } }>;
    status: string;
    role: string;
    counts: { pending_sellers: number; pending_buyers: number };
    paystackConfigured: boolean;
}

export default function WithdrawalsIndex({ withdrawals, status, role, counts, paystackConfigured }: WithdrawalsIndexProps) {
    const { flash } = usePage<{ flash?: { withdrawal_otp?: { withdrawal_id: number; transfer_code?: string | null } } }>().props;
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [otpWithdrawalId, setOtpWithdrawalId] = useState<number | null>(flash?.withdrawal_otp?.withdrawal_id ?? null);
    const [otp, setOtp] = useState('');

    const statusTabs = ['pending', 'processing', 'paid', 'rejected', 'all'];
    const roleTabs = [
        { value: 'all', label: 'Everyone' },
        { value: 'seller', label: `Sellers (${counts.pending_sellers})` },
        { value: 'buyer', label: `Buyers (${counts.pending_buyers})` },
    ];

    const statusColor = (value: string) => {
        switch (value) {
            case 'pending':
                return 'bg-amber-100 text-amber-800';
            case 'processing':
                return 'bg-blue-100 text-blue-800';
            case 'paid':
                return 'bg-green-100 text-green-800';
            case 'rejected':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    const roleBadge = (userRole?: string) => {
        if (userRole === 'seller') return 'bg-orange-100 text-orange-800';
        if (userRole === 'buyer') return 'bg-sky-100 text-sky-800';
        return 'bg-gray-100 text-gray-700';
    };

    const listHref = (nextStatus: string, nextRole: string = role) =>
        route('admin.withdrawals.index', { status: nextStatus, role: nextRole === 'all' ? undefined : nextRole });

    return (
        <AdminLayout title="Withdrawals" active="withdrawals">
            <Head title="Withdrawals" />

            <p className="mb-4 text-sm text-gray-500">
                Process seller earnings payouts and buyer wallet withdrawals. Choose Paystack for automated MoMo transfer or manual when you pay outside the system.
            </p>

            {!paystackConfigured && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Paystack is not configured. You can still use manual payout after sending MoMo yourself.
                </div>
            )}

            <div className="scrollbar-hide -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1">
                {roleTabs.map((tab) => (
                    <Link
                        key={tab.value}
                        href={listHref(status, tab.value)}
                        className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium ${
                            role === tab.value ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="scrollbar-hide -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
                {statusTabs.map((tab) => (
                    <Link
                        key={tab}
                        href={listHref(tab)}
                        className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {withdrawals.data.length === 0 ? (
                    <p className="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm">No withdrawals in this view.</p>
                ) : (
                    withdrawals.data.map((w) => {
                        const isSeller = w.user?.role === 'seller';

                        return (
                            <div key={w.id} className="rounded-xl bg-white p-4 shadow-sm">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-lg font-bold">{formatPrice(w.amount)}</p>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${statusColor(w.status)}`}>
                                                {w.status}
                                            </span>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${roleBadge(w.user?.role)}`}>
                                                {w.user?.role ?? 'user'}
                                            </span>
                                            {w.payout_channel && (
                                                <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium capitalize text-gray-700">
                                                    {w.payout_channel} payout
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {w.user?.name} · {w.account_name}
                                        </p>
                                        <p className="text-sm text-gray-500">
                                            {w.momo_number} · {w.network}
                                        </p>
                                        {isSeller && (
                                            <p className="mt-1 text-xs text-orange-600">Seller earnings withdrawal</p>
                                        )}
                                        {w.paystack_reference && (
                                            <p className="mt-1 text-xs text-gray-400">Paystack ref: {w.paystack_reference}</p>
                                        )}
                                        {w.failure_reason && (
                                            <p className="mt-1 text-xs text-red-600">{w.failure_reason}</p>
                                        )}
                                        {w.rejection_reason && (
                                            <p className="mt-1 text-xs text-red-600">{w.rejection_reason}</p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
                                        {w.status === 'pending' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    className="bg-green-600 hover:bg-green-700"
                                                    disabled={!paystackConfigured}
                                                    onClick={() => router.post(route('admin.withdrawals.process', w.id))}
                                                >
                                                    Paystack payout
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => router.post(route('admin.withdrawals.approve', w.id))}
                                                >
                                                    Manual payout
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => setRejectId(w.id)}>
                                                    Reject
                                                </Button>
                                            </>
                                        )}
                                        {w.status === 'processing' && (
                                            <>
                                                <Button size="sm" variant="outline" onClick={() => setOtpWithdrawalId(w.id)}>
                                                    Enter Paystack OTP
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => setRejectId(w.id)}>
                                                    Reject & refund
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Reject Withdrawal</h3>
                        <p className="mt-1 text-sm text-gray-500">Funds will be returned to the user&apos;s wallet.</p>
                        <Input className="mt-3" placeholder="Reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button
                                variant="destructive"
                                onClick={() => router.post(route('admin.withdrawals.reject', rejectId), { rejection_reason: reason }, { onSuccess: () => { setRejectId(null); setReason(''); } })}
                            >
                                Confirm
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}

            {otpWithdrawalId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Paystack OTP</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Enter the OTP sent to your Paystack business phone to authorize this payout.
                        </p>
                        <Input className="mt-3" placeholder="OTP code" value={otp} onChange={(e) => setOtp(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button
                                onClick={() => router.post(route('admin.withdrawals.finalize', otpWithdrawalId), { otp }, { onSuccess: () => { setOtpWithdrawalId(null); setOtp(''); } })}
                            >
                                Confirm payout
                            </Button>
                            <Button variant="outline" onClick={() => setOtpWithdrawalId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
