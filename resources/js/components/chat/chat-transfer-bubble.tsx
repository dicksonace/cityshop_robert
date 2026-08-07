import { ArrowLeftRight, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';
import type { ChatTransfer } from '@/types/chat';

function money(amount?: number | string | null): string {
    const n = typeof amount === 'number' ? amount : typeof amount === 'string' ? Number(amount) : NaN;
    if (!Number.isFinite(n)) return 'GH₵—';
    return `GH₵ ${n.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatReceiptDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

interface ChatTransferBubbleProps {
    transfer: ChatTransfer;
    mine: boolean;
    createdAt?: string;
    className?: string;
}

export default function ChatTransferBubble({ transfer, mine, createdAt, className }: ChatTransferBubbleProps) {
    const [open, setOpen] = useState(false);
    const fromName = (transfer.from_name || '').trim();
    const toName = (transfer.to_name || '').trim();
    const note = (transfer.note || '').trim();
    const ref = (transfer.reference || '').trim();
    const headline = mine ? 'You transferred' : 'Transferred to you';

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'block min-w-[15rem] w-full p-3 text-left transition hover:bg-green-50/70',
                    className,
                )}
            >
                <div className="flex gap-2.5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-600">
                        <ArrowLeftRight className="h-5 w-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-semibold text-gray-500">{headline}</p>
                        <p className="mt-0.5 text-xl font-black text-green-700">{money(transfer.amount)}</p>
                        {note ? <p className="mt-1 text-sm text-gray-800">{note}</p> : null}
                        {ref ? <p className="mt-1 text-[11px] text-gray-400">Ref: {ref}</p> : null}
                        <p className="mt-2 inline-flex items-center gap-0.5 text-xs font-bold text-orange-600">
                            View receipt
                            <ChevronRight className="h-3.5 w-3.5" />
                        </p>
                    </div>
                </div>
            </button>

            {open ? (
                <div className="fixed inset-0 z-[120] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4">
                    <button
                        type="button"
                        className="absolute inset-0 cursor-default"
                        aria-label="Close receipt"
                        onClick={() => setOpen(false)}
                    />
                    <div className="relative z-[1] w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-extrabold text-gray-900">Transfer receipt</h3>
                                <p className="text-sm text-gray-500">{mine ? 'Money sent' : 'Money received'}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="mb-5 flex flex-col items-center">
                            <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-green-100 text-green-600">
                                <ArrowLeftRight className="h-8 w-8" />
                            </div>
                            <p className="text-3xl font-black text-green-700">{money(transfer.amount)}</p>
                        </div>

                        <dl className="space-y-3 text-sm">
                            <div className="flex gap-3">
                                <dt className="w-24 shrink-0 text-gray-400">From</dt>
                                <dd className="font-semibold text-gray-900">{fromName || (mine ? 'You' : 'Sender')}</dd>
                            </div>
                            <div className="flex gap-3">
                                <dt className="w-24 shrink-0 text-gray-400">To</dt>
                                <dd className="font-semibold text-gray-900">{toName || (mine ? 'Recipient' : 'You')}</dd>
                            </div>
                            {ref ? (
                                <div className="flex gap-3">
                                    <dt className="w-24 shrink-0 text-gray-400">Reference</dt>
                                    <dd className="break-all font-semibold text-gray-900">{ref}</dd>
                                </div>
                            ) : null}
                            <div className="flex gap-3">
                                <dt className="w-24 shrink-0 text-gray-400">Date</dt>
                                <dd className="font-semibold text-gray-900">{formatReceiptDate(createdAt)}</dd>
                            </div>
                            {note ? (
                                <div className="flex gap-3">
                                    <dt className="w-24 shrink-0 text-gray-400">Note</dt>
                                    <dd className="font-semibold text-gray-900">{note}</dd>
                                </div>
                            ) : null}
                        </dl>

                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="mt-6 w-full rounded-xl bg-orange-500 px-4 py-3 text-sm font-bold text-white hover:bg-orange-600"
                        >
                            Close
                        </button>
                    </div>
                </div>
            ) : null}
        </>
    );
}
