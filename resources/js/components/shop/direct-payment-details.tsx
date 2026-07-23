import { useState } from 'react';

import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
import { BankPaymentLogo, bankPaymentTitle } from '@/lib/payment-method-display';
import { momoNetworkLabel, momoNumberFieldLabel, normalizeMomoNetworkId } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';

type DirectPaymentDetailsProps = {
    accountNumber: string;
    accountName: string;
    network?: string | null;
    isBank?: boolean;
    bankName?: string | null;
    className?: string;
    hint?: string | null;
};

export default function DirectPaymentDetails({
    accountNumber,
    accountName,
    network,
    isBank = false,
    bankName,
    className,
    hint,
}: DirectPaymentDetailsProps) {
    const [copied, setCopied] = useState(false);
    const networkId = normalizeMomoNetworkId(network);

    const copyNumber = async () => {
        if (!accountNumber) return;
        try {
            await navigator.clipboard.writeText(accountNumber);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // ignore
        }
    };

    const numberLabel = isBank ? 'Account number' : momoNumberFieldLabel(network, accountNumber);
    const networkTitle = isBank
        ? bankPaymentTitle({ bank_name: bankName })
        : networkId
          ? momoNetworkLabel(networkId)
          : 'Mobile Money';

    return (
        <div className={cn('space-y-3', className)}>
            <div className="overflow-hidden rounded-xl border border-dashed border-sky-300 bg-gradient-to-br from-sky-50/80 via-white to-cyan-50/40 shadow-sm">
                <div className="flex items-center gap-3 border-b border-sky-100/80 bg-white/70 px-3 py-2.5">
                    {isBank ? (
                        <BankPaymentLogo bankName={bankName || networkTitle} />
                    ) : (
                        <MomoNetworkLogo network={networkId ?? network} size="md" />
                    )}
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-sky-700/80">Pay to</p>
                        <p className="truncate text-sm font-bold text-gray-900">{networkTitle}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 p-3">
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{numberLabel}</p>
                        <p className="mt-0.5 break-all text-xl font-bold tracking-tight text-gray-900">{accountNumber}</p>
                        {accountName ? (
                            <>
                                <p className="mt-2.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Account name</p>
                                <div className="mt-0.5 space-y-0.5 text-sm font-bold leading-snug text-sky-700">
                                    {accountName
                                        .split(/\n|\s*\/\s*/)
                                        .map((part) => part.trim())
                                        .filter(Boolean)
                                        .map((line) => (
                                            <p key={line} className="uppercase">
                                                {line}
                                            </p>
                                        ))}
                                </div>
                            </>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        onClick={copyNumber}
                        className="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-gray-800"
                    >
                        {copied ? 'Copied' : 'Copy'}
                    </button>
                </div>
            </div>

            {hint ? <p className="text-xs text-gray-500">{hint}</p> : null}
        </div>
    );
}
