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
                    'inline-flex h-10 w-[4.25rem] shrink-0 items-center justify-center gap-1 rounded-lg bg-white px-1.5 shadow-sm ring-1 ring-gray-200',
                    className,
                )}
                aria-label="Visa and Mastercard"
            >
                {/* Official-style Visa wordmark */}
                <svg viewBox="0 0 48 16" className="h-4 w-8" role="img" aria-hidden>
                    <title>Visa</title>
                    <rect width="48" height="16" rx="2" fill="#1A1F71" />
                    <text
                        x="24"
                        y="11.5"
                        textAnchor="middle"
                        fill="#fff"
                        fontFamily="Arial, Helvetica, sans-serif"
                        fontStyle="italic"
                        fontWeight="700"
                        fontSize="9"
                        letterSpacing="0.5"
                    >
                        VISA
                    </text>
                </svg>
                {/* Official-style Mastercard interlocking circles */}
                <svg viewBox="0 0 38 24" className="h-5 w-7" role="img" aria-hidden>
                    <title>Mastercard</title>
                    <circle cx="14" cy="12" r="9" fill="#EB001B" />
                    <circle cx="24" cy="12" r="9" fill="#F79E1B" />
                    <path
                        d="M19 5.6c1.9 1.6 3.1 4 3.1 6.4s-1.2 4.8-3.1 6.4c-1.9-1.6-3.1-4-3.1-6.4s1.2-4.8 3.1-6.4z"
                        fill="#FF5F00"
                    />
                </svg>
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
