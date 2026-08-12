import { AlertCircle } from 'lucide-react';

import { formatPrice } from '@/types/marketplace';

export function withdrawalBalanceMessage(
    amount: number,
    fee: number,
    available: number,
): string | null {
    if (amount <= 0) {
        return null;
    }

    const total = amount + fee;
    if (total <= available + 1e-9) {
        return null;
    }

    return `Insufficient balance. Available: ${formatPrice(available)}`;
}

export default function WithdrawalBalanceAlert({
    amount,
    fee,
    available,
    className = '',
}: {
    amount: number;
    fee: number;
    available: number;
    className?: string;
}) {
    const message = withdrawalBalanceMessage(amount, fee, available);
    if (!message) {
        return null;
    }

    return (
        <div
            className={`flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 ${className}`}
            role="alert"
        >
            <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-white">
                <AlertCircle className="h-4 w-4" aria-hidden />
            </span>
            <p className="font-medium leading-snug">{message}</p>
        </div>
    );
}
