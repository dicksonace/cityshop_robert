import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';
import { formatOrderStatus, orderFulfillmentSteps } from '@/types/marketplace';

interface OrderProgressProps {
    status: string;
    className?: string;
}

const stepIndex = (status: string): number => {
    const map: Record<string, number> = {
        pending: 0,
        processing: 0,
        packed: 1,
        shipped: 2,
        awaiting_confirmation: 3,
        delivered: 4,
    };

    return map[status] ?? 0;
};

export default function OrderProgress({ status, className }: OrderProgressProps) {
    const current = stepIndex(status);
    const terminal = ['cancelled', 'refunded'].includes(status);

    if (terminal) {
        return (
            <p className={cn('text-sm font-medium capitalize text-gray-600', className)}>
                {formatOrderStatus(status)}
            </p>
        );
    }

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center justify-between gap-1">
                {orderFulfillmentSteps.map((step, i) => {
                    const done = i < current;
                    const active = i === current;

                    return (
                        <div key={step.key} className="flex flex-1 flex-col items-center">
                            <div
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                                    done && 'bg-green-500 text-white',
                                    active && 'bg-orange-500 text-white ring-4 ring-orange-100',
                                    !done && !active && 'bg-gray-100 text-gray-400',
                                )}
                            >
                                {done ? <Check className="h-4 w-4" /> : i + 1}
                            </div>
                            <p
                                className={cn(
                                    'mt-1.5 hidden text-center text-[10px] leading-tight sm:block',
                                    active ? 'font-semibold text-orange-600' : 'text-gray-500',
                                )}
                            >
                                {step.label}
                            </p>
                        </div>
                    );
                })}
            </div>
            <div className="flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                <div
                    className="rounded-full bg-orange-500 transition-all"
                    style={{ width: `${(current / (orderFulfillmentSteps.length - 1)) * 100}%` }}
                />
            </div>
            <p className="text-center text-sm font-medium text-gray-800 sm:hidden">
                {formatOrderStatus(status)}
            </p>
        </div>
    );
}
