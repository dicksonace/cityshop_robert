import { cn } from '@/lib/utils';

type MomoNetworkLogoProps = {
    network?: string | null;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
};

const sizeMap = {
    sm: 'h-8 w-8 text-[10px]',
    md: 'h-10 w-10 text-xs',
    lg: 'h-12 w-12 text-sm',
};

/** Brand-colored network marks (not official trademarks) for quick recognition. */
export default function MomoNetworkLogo({ network, size = 'md', className }: MomoNetworkLogoProps) {
    const id = (network ?? '').toLowerCase().replace(/[\s_-]/g, '');
    const box = cn(
        'inline-flex shrink-0 items-center justify-center rounded-xl font-black tracking-tight text-white shadow-sm',
        sizeMap[size],
        className,
    );

    if (id.includes('telecel') || id.includes('vodafone')) {
        return (
            <span className={cn(box, 'bg-gradient-to-br from-red-600 to-rose-700')} title="Telecel Cash" aria-label="Telecel Cash">
                TC
            </span>
        );
    }

    if (id.includes('airtel') || id.includes('tigo')) {
        return (
            <span
                className={cn(box, 'bg-gradient-to-br from-red-500 via-red-600 to-sky-600')}
                title="AirtelTigo Money"
                aria-label="AirtelTigo Money"
            >
                AT
            </span>
        );
    }

    if (id.includes('mtn') || id === '') {
        return (
            <span className={cn(box, 'bg-gradient-to-br from-yellow-400 to-amber-500 text-gray-900')} title="MTN MoMo" aria-label="MTN MoMo">
                MTN
            </span>
        );
    }

    return (
        <span className={cn(box, 'bg-gradient-to-br from-slate-600 to-slate-800')} title="Mobile Money" aria-label="Mobile Money">
            MoMo
        </span>
    );
}
