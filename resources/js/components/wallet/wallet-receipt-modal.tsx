import { X } from 'lucide-react';
import { useState } from 'react';

import { formatPrice, formatWalletTransactionType, WalletTransaction } from '@/types/marketplace';

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

interface WalletReceiptModalProps {
    tx: WalletTransaction;
    open: boolean;
    onClose: () => void;
}

export function WalletReceiptModal({ tx, open, onClose }: WalletReceiptModalProps) {
    if (!open) return null;
    const isCredit = tx.amount > 0;

    return (
        <div className="fixed inset-0 z-[120] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4">
            <button type="button" className="absolute inset-0 cursor-default" aria-label="Close" onClick={onClose} />
            <div className="relative z-[1] w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-extrabold text-gray-900">Transaction receipt</h3>
                        <p className="text-sm text-gray-500">{formatWalletTransactionType(tx.type)}</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <div className="mb-5 flex flex-col items-center">
                    <p className={`text-3xl font-black ${isCredit ? 'text-green-600' : 'text-red-600'}`}>
                        {isCredit ? '+' : ''}
                        {formatPrice(tx.amount)}
                    </p>
                </div>

                <dl className="space-y-3 text-sm">
                    {tx.description ? (
                        <div className="flex gap-3">
                            <dt className="w-28 shrink-0 text-gray-400">Details</dt>
                            <dd className="font-semibold text-gray-900">{tx.description}</dd>
                        </div>
                    ) : null}
                    {tx.reference ? (
                        <div className="flex gap-3">
                            <dt className="w-28 shrink-0 text-gray-400">Reference</dt>
                            <dd className="break-all font-semibold text-gray-900">{tx.reference}</dd>
                        </div>
                    ) : null}
                    <div className="flex gap-3">
                        <dt className="w-28 shrink-0 text-gray-400">Date</dt>
                        <dd className="font-semibold text-gray-900">{formatDate(tx.created_at)}</dd>
                    </div>
                    <div className="flex gap-3">
                        <dt className="w-28 shrink-0 text-gray-400">Before balance</dt>
                        <dd className="font-semibold text-gray-900">{formatPrice(tx.balance_before ?? 0)}</dd>
                    </div>
                    <div className="flex gap-3">
                        <dt className="w-28 shrink-0 text-gray-400">After balance</dt>
                        <dd className="font-semibold text-gray-900">{formatPrice(tx.balance_after ?? 0)}</dd>
                    </div>
                </dl>

                <button
                    type="button"
                    onClick={onClose}
                    className="mt-6 w-full rounded-xl bg-orange-500 px-4 py-3 text-sm font-bold text-white hover:bg-orange-600"
                >
                    Close
                </button>
            </div>
        </div>
    );
}

export function WalletTransactionReceiptButton({ tx }: { tx: WalletTransaction }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mt-1 text-left text-xs font-bold text-orange-600 hover:underline"
            >
                View receipt
            </button>
            <WalletReceiptModal tx={tx} open={open} onClose={() => setOpen(false)} />
        </>
    );
}
