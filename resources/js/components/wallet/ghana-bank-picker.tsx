import { GHANA_BANKS } from '@/lib/ghana-banks';
import { cn } from '@/lib/utils';

interface GhanaBankPickerProps {
    value: string;
    onChange: (bankId: string) => void;
    className?: string;
    hideHeading?: boolean;
}

/** Radio-style Ghana bank list for withdraw / payout forms. */
export default function GhanaBankPicker({ value, onChange, className, hideHeading = false }: GhanaBankPickerProps) {
    return (
        <div className={cn('space-y-2', className)}>
            {!hideHeading && <p className="text-sm font-semibold text-gray-900">Select Bank</p>}
            <div className="max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white">
                {GHANA_BANKS.map((bank, index) => {
                    const selected = value === bank.id;
                    return (
                        <label
                            key={bank.id}
                            className={cn(
                                'flex cursor-pointer items-center gap-3 px-3 py-3 transition hover:bg-orange-50/60',
                                selected && 'bg-orange-50',
                                index > 0 && 'border-t border-gray-100',
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
                            <input
                                type="radio"
                                name="ghana_bank"
                                value={bank.id}
                                checked={selected}
                                onChange={() => onChange(bank.id)}
                                className="sr-only"
                            />
                            <span className={cn('text-sm', selected ? 'font-semibold text-gray-900' : 'font-medium text-gray-800')}>
                                {bank.label}
                            </span>
                        </label>
                    );
                })}
            </div>
        </div>
    );
}
