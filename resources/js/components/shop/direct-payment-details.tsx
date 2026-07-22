import { Megaphone } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

/** MoMo note buyers must enter so sellers can spot CityShop payments. */
export const DIRECT_PAYMENT_NOTE = 'Cityshop';

type DirectPaymentDetailsProps = {
    accountNumber: string;
    accountName: string;
    isBank?: boolean;
    className?: string;
    hint?: string | null;
};

export default function DirectPaymentDetails({
    accountNumber,
    accountName,
    isBank = false,
    className,
    hint,
}: DirectPaymentDetailsProps) {
    const [copied, setCopied] = useState<'number' | 'note' | null>(null);

    const copyText = async (text: string, key: 'number' | 'note') => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            setCopied(key);
            setTimeout(() => setCopied(null), 1500);
        } catch {
            // ignore
        }
    };

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center gap-3 rounded-xl border border-dashed border-sky-300 bg-sky-50/40 p-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        {isBank ? 'Account number' : 'MoMo number'}
                    </p>
                    <p className="mt-0.5 text-xl font-bold tracking-tight text-gray-900">{accountNumber}</p>
                    {accountName ? (
                        <>
                            <p className="mt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Account name</p>
                            <p className="mt-0.5 text-sm font-bold uppercase text-sky-600">{accountName}</p>
                        </>
                    ) : null}
                </div>
                <button
                    type="button"
                    onClick={() => copyText(accountNumber, 'number')}
                    className="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white"
                >
                    {copied === 'number' ? 'Copied' : 'Copy'}
                </button>
            </div>

            <div className="flex items-center gap-3 rounded-xl border border-dashed border-red-400 bg-red-50/50 p-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-bold uppercase tracking-wide text-red-600">
                        Your unique reference (must use)
                    </p>
                    <div className="mt-2 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 shadow-sm ring-1 ring-red-100">
                        <Megaphone className="h-4 w-4 shrink-0 text-orange-500" aria-hidden />
                        <span className="text-base font-bold text-gray-900">{DIRECT_PAYMENT_NOTE}</span>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => copyText(DIRECT_PAYMENT_NOTE, 'note')}
                    className="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white"
                >
                    {copied === 'note' ? 'Copied' : 'Copy'}
                </button>
            </div>

            {hint ? <p className="text-xs text-gray-500">{hint}</p> : null}
        </div>
    );
}
