import { useMemo, useState } from 'react';

import { GHANA_BANKS } from '@/lib/ghana-banks';
import { cn } from '@/lib/utils';

interface GhanaBankPickerProps {
    value: string;
    onChange: (bankId: string) => void;
    className?: string;
    hideHeading?: boolean;
}

/**
 * Ghana bank list for withdraw / payout forms.
 * Buttons (not sr-only radios) so names are not clipped inside overflow-x-clip layouts.
 */
export default function GhanaBankPicker({ value, onChange, className, hideHeading = false }: GhanaBankPickerProps) {
    const [query, setQuery] = useState('');
    const banks = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return GHANA_BANKS;
        }

        return GHANA_BANKS.filter(
            (bank) => bank.label.toLowerCase().includes(q) || bank.id.replace(/_/g, ' ').includes(q),
        );
    }, [query]);

    return (
        <div className={cn('min-w-0 space-y-2', className)}>
            {!hideHeading && <p className="text-sm font-semibold text-gray-900">Select Bank</p>}
            <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search bank"
                className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none ring-orange-500 placeholder:text-gray-400 focus:border-orange-400 focus:ring-2"
                autoComplete="off"
            />
            <div className="max-h-72 min-w-0 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white">
                {banks.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-gray-500">No bank matches that search.</p>
                ) : (
                    banks.map((bank) => {
                        const selected = value === bank.id;

                        return (
                            <button
                                key={bank.id}
                                type="button"
                                onClick={() => onChange(bank.id)}
                                className={cn(
                                    'flex w-full min-w-0 items-center gap-3 px-3 py-3 text-left transition hover:bg-orange-50/60',
                                    selected && 'bg-orange-50',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                        selected ? 'border-orange-500' : 'border-gray-300',
                                    )}
                                >
                                    {selected ? <span className="h-2.5 w-2.5 rounded-full bg-orange-500" /> : null}
                                </span>
                                <span
                                    className={cn(
                                        'min-w-0 flex-1 break-words text-sm leading-snug',
                                        selected ? 'font-semibold text-gray-900' : 'font-medium text-gray-800',
                                    )}
                                >
                                    {bank.label}
                                </span>
                            </button>
                        );
                    })
                )}
            </div>
        </div>
    );
}
