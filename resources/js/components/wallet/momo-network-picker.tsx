import { Check, ChevronRight } from 'lucide-react';
import { useState } from 'react';

import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
import { MOMO_NETWORKS, momoNetworkLabel } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';

interface MomoNetworkPickerProps {
    value: string;
    onChange: (network: string) => void;
    label?: string;
    hint?: string;
    className?: string;
    /** When set, networks not in this list are shown disabled and cannot be selected. */
    enabledNetworks?: string[];
    /** grid = three chips; list = stacked tiles; selected = one network + change action. */
    variant?: 'grid' | 'list' | 'selected';
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
    const [changing, setChanging] = useState(false);
    const selectedMeta = MOMO_NETWORKS.find((n) => n.id === value);

    if (variant === 'selected' && value && selectedMeta && !changing) {
        return (
            <div className={className}>
                <button
                    type="button"
                    onClick={() => setChanging(true)}
                    className={cn(
                        'flex w-full items-center gap-3 rounded-xl border-2 px-3 py-2.5 text-left transition hover:opacity-95',
                        selectedMeta.selectedClass,
                    )}
                >
                    <MomoNetworkLogo network={value} size="md" />
                    <span className="min-w-0 flex-1 text-sm font-bold text-gray-900">
                        {momoNetworkLabel(value)}
                    </span>
                    <span className="text-xs font-bold text-gray-600">Change</span>
                    <ChevronRight className="h-4 w-4 shrink-0 text-gray-500" />
                </button>
            </div>
        );
    }

    if (variant === 'list' || (variant === 'selected' && changing)) {
        return (
            <div className={className}>
                {variant === 'selected' ? (
                    <p className="mb-2 text-xs font-semibold text-gray-500">Choose network</p>
                ) : (
                    <>
                        <p className="text-[15px] font-extrabold text-gray-900">{label}</p>
                        {hint && <p className="mt-1 text-[13px] leading-snug text-gray-500">{hint}</p>}
                    </>
                )}
                <div className={cn(variant === 'selected' ? 'space-y-2' : 'mt-3 space-y-2')}>
                    {MOMO_NETWORKS.map((network) => {
                        const selected = value === network.id;
                        const disabled = enabledNetworks ? !enabledNetworks.includes(network.id) : false;

                        return (
                            <button
                                key={network.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => {
                                    if (disabled) return;
                                    onChange(network.id);
                                    if (variant === 'selected') setChanging(false);
                                }}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-[14px] px-3 py-3 text-left transition',
                                    disabled && 'cursor-not-allowed opacity-40',
                                    !disabled &&
                                        (selected
                                            ? cn('border-2', network.selectedClass)
                                            : 'border-[1.4px] border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'),
                                )}
                            >
                                <MomoNetworkLogo network={network.id} size="md" />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold text-gray-900">{network.label}</span>
                                </span>
                                <span
                                    className={cn(
                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                        selected
                                            ? (checkTone[network.id] ?? 'border-orange-500 bg-orange-500 text-white')
                                            : 'border-gray-300 bg-white',
                                    )}
                                >
                                    {selected ? <Check className="h-3 w-3" strokeWidth={3} /> : null}
                                </span>
                            </button>
                        );
                    })}
                </div>
                {variant === 'selected' && (
                    <button
                        type="button"
                        onClick={() => setChanging(false)}
                        className="mt-2 text-xs font-semibold text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </button>
                )}
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
