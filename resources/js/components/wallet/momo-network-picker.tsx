import { Check } from 'lucide-react';

import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
import { MOMO_NETWORKS } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';

interface MomoNetworkPickerProps {
    value: string;
    onChange: (network: string) => void;
    label?: string;
    hint?: string;
    className?: string;
    /** When set, networks not in this list are shown disabled and cannot be selected. */
    enabledNetworks?: string[];
    /** App-style stacked tiles (buyer withdraw page). */
    variant?: 'grid' | 'list';
}

const checkTone: Record<string, string> = {
    mtn: 'border-yellow-500 bg-yellow-500 text-white',
    telecel: 'border-red-500 bg-red-500 text-white',
    airteltigo: 'border-blue-500 bg-blue-500 text-white',
};

export default function MomoNetworkPicker({
    value,
    onChange,
    label = 'Mobile money network',
    hint = 'Choose the network for your MoMo wallet — MTN is most common.',
    className,
    enabledNetworks,
    variant = 'grid',
}: MomoNetworkPickerProps) {
    if (variant === 'list') {
        return (
            <div className={className}>
                <p className="text-[15px] font-extrabold text-gray-900">{label}</p>
                {hint && <p className="mt-1 text-[13px] leading-snug text-gray-500">{hint}</p>}
                <div className="mt-3 space-y-2">
                    {MOMO_NETWORKS.map((network) => {
                        const selected = value === network.id;

                        return (
                            <button
                                key={network.id}
                                type="button"
                                onClick={() => onChange(network.id)}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-[14px] px-3 py-3 text-left transition',
                                    selected
                                        ? cn('border-2', network.selectedClass)
                                        : 'border-[1.4px] border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
                                )}
                            >
                                <MomoNetworkLogo network={network.id} size="md" />
                                <span className="min-w-0 flex-1">
                                    {network.id === 'mtn' && (
                                        <span className={cn('block text-[10px] font-extrabold tracking-[0.07em]', selected ? network.accent : 'text-gray-400')}>
                                            MOST COMMON
                                        </span>
                                    )}
                                    <span className="block text-sm font-semibold text-gray-900">{network.label}</span>
                                </span>
                                <span
                                    className={cn(
                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                        selected ? (checkTone[network.id] ?? 'border-orange-500 bg-orange-500 text-white') : 'border-gray-300 bg-white',
                                    )}
                                >
                                    {selected ? <Check className="h-3 w-3" strokeWidth={3} /> : null}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>
        );
    }

    return (
        <div className={className}>
            <div className="mb-3 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-gray-900">{label}</p>
                {value && hint ? <p className="text-xs text-gray-500">{hint}</p> : null}
            </div>
            <div className="grid grid-cols-3 gap-2">
                {MOMO_NETWORKS.map((network) => {
                    const selected = value === network.id;
                    const disabled = enabledNetworks ? !enabledNetworks.includes(network.id) : false;

                    return (
                        <button
                            key={network.id}
                            type="button"
                            disabled={disabled}
                            onClick={() => !disabled && onChange(network.id)}
                            className={cn(
                                'flex min-h-[4.5rem] flex-col items-center justify-center gap-1.5 rounded-xl border-2 px-2 py-2.5 text-center transition',
                                disabled && 'cursor-not-allowed opacity-40',
                                !disabled &&
                                    (selected
                                        ? network.selectedClass
                                        : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'),
                            )}
                        >
                            <MomoNetworkLogo network={network.id} size="sm" />
                            <span className="text-xs font-bold text-gray-900">
                                {network.id === 'airteltigo' ? 'AT' : network.shortLabel.split(' ')[0]}
                            </span>
                            {selected ? (
                                <span className="text-[10px] font-bold uppercase tracking-wide text-gray-500">Selected</span>
                            ) : null}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
