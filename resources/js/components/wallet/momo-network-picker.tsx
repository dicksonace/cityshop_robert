import { Smartphone } from 'lucide-react';

import { MOMO_NETWORKS } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';

interface MomoNetworkPickerProps {
    value: string;
    onChange: (network: string) => void;
    label?: string;
    hint?: string;
    className?: string;
}

export default function MomoNetworkPicker({
    value,
    onChange,
    label = 'Mobile money network',
    hint = 'Choose the network for your MoMo wallet — MTN is most common.',
    className,
}: MomoNetworkPickerProps) {
    return (
        <div className={className}>
            <div className="mb-3 flex items-center gap-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
                    <Smartphone className="h-5 w-5" />
                </div>
                <div>
                    <p className="text-sm font-semibold text-gray-900">{label}</p>
                    {hint && <p className="text-xs text-gray-500">{hint}</p>}
                </div>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                {MOMO_NETWORKS.map((network) => {
                    const selected = value === network.id;

                    return (
                        <button
                            key={network.id}
                            type="button"
                            onClick={() => onChange(network.id)}
                            className={cn(
                                'flex min-h-[4.5rem] flex-col items-start justify-center rounded-xl border-2 px-4 py-3 text-left transition',
                                selected ? network.selectedClass : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
                            )}
                        >
                            <span className={cn('text-xs font-bold uppercase tracking-wide', selected ? network.accent : 'text-gray-400')}>
                                {network.id === 'mtn' ? 'Recommended' : 'MoMo'}
                            </span>
                            <span className="mt-1 text-sm font-semibold text-gray-900">{network.shortLabel}</span>
                            <span className="text-xs text-gray-500">{network.label}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
