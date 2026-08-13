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
            <div className="mb-3 flex items-center gap-2">
                <MomoNetworkLogo network={value || 'mtn'} size="sm" />
                <div>
                    <p className="text-sm font-semibold text-gray-900">{label}</p>
                    {hint && <p className="text-xs text-gray-500">{hint}</p>}
                </div>
            </div>
            <div className="grid grid-cols-3 gap-2">
                {MOMO_NETWORKS.map((network) => {
                    const selected = value === network.id;

                    return (
                        <button
                            key={network.id}
                            type="button"
                            onClick={() => onChange(network.id)}
                            className={cn(
                                'flex min-h-[3.25rem] flex-col items-center justify-center gap-1 rounded-xl border-2 px-1.5 py-2 text-center transition sm:min-h-[4.25rem] sm:flex-row sm:items-center sm:gap-3 sm:px-3 sm:py-3 sm:text-left',
                                selected ? network.selectedClass : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
                            )}
                        >
                            <MomoNetworkLogo network={network.id} size="sm" />
                            <span className="min-w-0">
                                <span className={cn('hidden text-[10px] font-bold uppercase tracking-wide sm:block', selected ? network.accent : 'text-gray-400')}>
                                    {network.id === 'mtn' ? 'Recommended' : 'MoMo'}
                                </span>
                                <span className="block text-xs font-semibold text-gray-900 sm:mt-0.5 sm:text-sm">{network.shortLabel}</span>
                                <span className="hidden text-xs text-gray-500 sm:block">{network.label}</span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
