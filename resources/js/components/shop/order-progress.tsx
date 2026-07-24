import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';
import { codOrderFulfillmentSteps, formatOrderStatus, orderFulfillmentSteps } from '@/types/marketplace';

interface OrderProgressProps {
    status: string;
    paymentMethod?: string | null;
    className?: string;
}

/** Paid flow: Processing → Packing → Out for delivery → Delivered → Completed (no Call). */
const paidStepIndex = (status: string): number => {
    const map: Record<string, number> = {
        pending: 0,
        processing: 0,
        // Legacy: paid orders should never be call_confirmed; treat as packing.
        call_confirmed: 1,
        packed: 1,
        shipped: 2,
        awaiting_confirmation: 3,
        delivered: 4,
    };

    return map[status] ?? 0;
};

/** COD flow: Cash on delivery → Processing → Seller called → Packing → On the way → Completed. */
const codStepIndex = (status: string): number => {
    const map: Record<string, number> = {
        pending: 0,
        processing: 1,
        call_confirmed: 2,
        packed: 3,
        shipped: 4,
        awaiting_confirmation: 4,
        delivered: 5,
    };

    return map[status] ?? 0;
};

export default function OrderProgress({ status, paymentMethod, className }: OrderProgressProps) {
    const isCod = paymentMethod === 'cash';
    const steps = isCod ? codOrderFulfillmentSteps : orderFulfillmentSteps;
    const current = isCod ? codStepIndex(status) : paidStepIndex(status);
    const isComplete = status === 'delivered';
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
            <div className="flex items-start justify-between gap-0.5 sm:gap-1">
                {steps.map((step, i) => {
                    const done = isComplete ? i <= current : i < current;
                    const active = !isComplete && i === current;

                    return (
                        <div key={step.key} className="flex min-w-0 flex-1 flex-col items-center">
                            <div
                                className={cn(
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    done && 'bg-green-500 text-white',
                                    active && 'bg-orange-500 text-white ring-4 ring-orange-100',
                                    !done && !active && 'bg-gray-100 text-gray-400',
                                )}
                            >
                                {done ? <Check className="h-4 w-4" /> : i + 1}
                            </div>
                            <p
                                className={cn(
                                    'mt-1.5 w-full text-center text-[9px] leading-tight sm:text-[10px]',
                                    active || (isComplete && i === current) ? 'font-semibold text-orange-600' : 'text-gray-500',
                                    isComplete && i === current && 'text-emerald-700',
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
                    className={cn('rounded-full transition-all', isComplete ? 'bg-emerald-500' : 'bg-orange-500')}
                    style={{
                        width: `${Math.min(100, (current / Math.max(1, steps.length - 1)) * 100)}%`,
                    }}
                />
            </div>
            <p className="text-center text-sm font-medium text-gray-800">
                {isCod && status === 'pending' ? 'Cash on delivery' : formatOrderStatus(status)}
            </p>
        </div>
    );
}
