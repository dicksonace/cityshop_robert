import { LoaderCircle, Printer } from 'lucide-react';
import { useState } from 'react';

import {
    fetchStatementTransactions,
    printWalletStatement,
    STATEMENT_PERIODS,
    type StatementPeriod,
} from '@/lib/wallet-statement';

type Props = {
    accountName: string;
    accountMobile?: string | null;
    closingBalance?: number | null;
    currencyFilter?: 'all' | 'GHS' | 'RMB';
    disabled?: boolean;
};

export default function WalletStatementButton({
    accountName,
    accountMobile,
    closingBalance,
    currencyFilter = 'all',
    disabled = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const runStatement = async (period: StatementPeriod) => {
        setLoading(true);
        setError(null);
        setOpen(false);

        try {
            const since =
                period.days != null
                    ? new Date(Date.now() - period.days * 24 * 60 * 60 * 1000)
                    : null;
            const rows = await fetchStatementTransactions(since, currencyFilter);

            if (rows.length === 0) {
                setError('No transactions in that period.');
                return;
            }

            printWalletStatement({
                accountName,
                accountMobile,
                periodLabel: period.label,
                transactions: rows,
                closingBalance,
            });
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not build the statement. Try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <button
                type="button"
                disabled={disabled || loading}
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-orange-600 hover:bg-orange-50 disabled:opacity-60"
            >
                {loading ? (
                    <LoaderCircle className="h-3.5 w-3.5 animate-spin" />
                ) : (
                    <Printer className="h-3.5 w-3.5" />
                )}
                {loading ? 'Preparing…' : 'Statement'}
            </button>

            {error && <p className="mt-2 w-full text-xs text-red-600">{error}</p>}

            {open && (
                <div className="fixed inset-0 z-[120] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4">
                    <button
                        type="button"
                        className="absolute inset-0 cursor-default"
                        aria-label="Close"
                        onClick={() => setOpen(false)}
                    />
                    <div className="relative z-[1] w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                        <h3 className="text-lg font-extrabold text-gray-900">Transaction statement</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Choose a period. You can print it or save it as a PDF.
                        </p>
                        <ul className="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-100">
                            {STATEMENT_PERIODS.map((period) => (
                                <li key={period.id}>
                                    <button
                                        type="button"
                                        onClick={() => runStatement(period)}
                                        className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-gray-900 hover:bg-gray-50"
                                    >
                                        {period.label}
                                        <span className="text-gray-300">›</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="mt-4 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
