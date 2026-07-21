import { cn } from '@/lib/utils';

interface PaymentMethodIconProps {
    method: 'momo' | 'card' | 'cash' | 'wallet';
    className?: string;
}

/** Compact brand mark for checkout payment options (MTN MoMo, cards, etc.). */
export default function PaymentMethodIcon({ method, className }: PaymentMethodIconProps) {
    if (method === 'momo') {
        return (
            <span
                className={cn(
                    'inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#FFCC00] shadow-sm ring-1 ring-yellow-600/20',
                    className,
                )}
                aria-hidden
            >
                <svg viewBox="0 0 40 40" className="h-8 w-8" role="img">
                    <title>MTN</title>
                    <text
                        x="20"
                        y="26"
                        textAnchor="middle"
                        fontFamily="Arial Black, Arial, sans-serif"
                        fontWeight="900"
                        fontSize="14"
                        fill="#000"
                        letterSpacing="-0.5"
                    >
                        MTN
                    </text>
                </svg>
            </span>
        );
    }

    if (method === 'card') {
        return (
            <span
                className={cn(
                    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-[9px] font-bold tracking-tight text-white shadow-sm',
                    className,
                )}
                aria-hidden
            >
                <span className="leading-none">
                    VISA
                    <br />
                    <span className="text-[8px] font-semibold text-amber-300">MC</span>
                </span>
            </span>
        );
    }

    if (method === 'wallet') {
        return (
            <span
                className={cn(
                    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-xs font-bold text-orange-600 shadow-sm',
                    className,
                )}
                aria-hidden
            >
                ₵
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-[10px] font-bold text-emerald-700 shadow-sm',
                className,
            )}
            aria-hidden
        >
            COD
        </span>
    );
}
