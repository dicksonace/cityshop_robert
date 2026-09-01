import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const selectButtonClass =
    'flex h-10 w-full items-center justify-between rounded-md border border-input bg-white px-3 py-2 text-left text-base text-gray-900 ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

interface SearchableLocationSelectProps {
    id: string;
    label: string;
    value: string;
    options: string[];
    placeholder: string;
    searchPlaceholder: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}

export default function SearchableLocationSelect({
    id,
    label,
    value,
    options,
    placeholder,
    searchPlaceholder,
    disabled = false,
    onChange,
}: SearchableLocationSelectProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const matches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter((item) => item.toLowerCase().includes(q));
    }, [options, query]);

    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <button
                id={id}
                type="button"
                disabled={disabled}
                className={cn(selectButtonClass, 'mt-1')}
                onClick={() => {
                    if (disabled) return;
                    setQuery('');
                    setOpen(true);
                }}
            >
                <span className={cn(!value && 'text-muted-foreground')}>{value || placeholder}</span>
                <span aria-hidden className="text-muted-foreground">
                    ▾
                </span>
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4">
                    <button
                        type="button"
                        aria-label="Close"
                        className="absolute inset-0"
                        onClick={() => setOpen(false)}
                    />
                    <div className="relative z-10 flex max-h-[78vh] w-full max-w-md flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
                        <div className="border-b border-gray-100 px-4 py-4">
                            <h2 className="text-lg font-bold text-gray-900">{label}</h2>
                            <div className="relative mt-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <Input
                                    autoFocus
                                    value={query}
                                    placeholder={searchPlaceholder}
                                    className="pl-9"
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                            </div>
                        </div>
                        <ul className="min-h-0 flex-1 overflow-y-auto py-1">
                            {matches.length === 0 ? (
                                <li className="px-4 py-8 text-center text-sm text-gray-500">No matches</li>
                            ) : (
                                matches.map((item) => {
                                    const selected = item === value;
                                    return (
                                        <li key={item}>
                                            <button
                                                type="button"
                                                className={cn(
                                                    'flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-orange-50',
                                                    selected && 'bg-orange-50 font-semibold text-orange-600',
                                                )}
                                                onClick={() => {
                                                    onChange(item);
                                                    setOpen(false);
                                                }}
                                            >
                                                <span>{item}</span>
                                                {selected && <span aria-hidden>✓</span>}
                                            </button>
                                        </li>
                                    );
                                })
                            )}
                        </ul>
                    </div>
                </div>
            )}
        </div>
    );
}
