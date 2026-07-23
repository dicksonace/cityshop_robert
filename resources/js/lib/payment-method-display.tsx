import { Landmark } from 'lucide-react';

/**
 * Detect bank receive details for Pay-to-seller / top-up cards.
 * Prefer explicit `type === 'bank'`; also treat bank_name as bank even if a leftover MoMo network was saved.
 */
export function isBankPaymentMethod(method?: {
    type?: string | null;
    bank_name?: string | null;
    network?: string | null;
} | null): boolean {
    if (!method) {
        return false;
    }

    if (method.type === 'bank') {
        return true;
    }

    const bankName = (method.bank_name ?? '').trim();
    if (bankName !== '') {
        return true;
    }

    return false;
}

export function bankPaymentTitle(method?: {
    bank_name?: string | null;
    account_name?: string | null;
} | null): string {
    const bank = (method?.bank_name ?? '').trim();
    if (bank) {
        return bank;
    }

    return 'Bank transfer';
}

export function BankPaymentLogo({ bankName, className }: { bankName?: string | null; className?: string }) {
    const initials = (bankName ?? 'BANK')
        .replace(/[^a-zA-Z0-9\s]/g, ' ')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('') || 'BK';

    return (
        <span
            className={
                className
                ?? 'inline-flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-800 text-white shadow-sm'
            }
            title={bankName || 'Bank'}
        >
            <Landmark className="h-3.5 w-3.5 opacity-90" aria-hidden />
            <span className="mt-0.5 text-[9px] font-black leading-none tracking-wide">{initials.slice(0, 3)}</span>
        </span>
    );
}
