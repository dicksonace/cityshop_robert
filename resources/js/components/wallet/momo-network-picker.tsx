import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
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
                <MomoNetworkLogo network={value || 'mtn'} size="sm" />
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
                                'flex min-h-[4.5rem] items-center gap-3 rounded-xl border-2 px-3 py-3 text-left transition',
                                selected ? network.selectedClass : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
                            )}
                        >
                            <MomoNetworkLogo network={network.id} size="sm" />
                            <span className="min-w-0">
                                <span className={cn('block text-[10px] font-bold uppercase tracking-wide', selected ? network.accent : 'text-gray-400')}>
                                    {network.id === 'mtn' ? 'Recommended' : 'MoMo'}
                                </span>
                                <span className="mt-0.5 block text-sm font-semibold text-gray-900">{network.shortLabel}</span>
                                <span className="block text-xs text-gray-500">{network.label}</span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
