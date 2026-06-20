import { Star } from 'lucide-react';

import { cn } from '@/lib/utils';

interface RatingDisplayProps {
    rating: number;
    reviewCount?: number;
    size?: 'sm' | 'md';
    compact?: boolean;
    className?: string;
}

export default function RatingDisplay({ rating, reviewCount = 0, size = 'sm', compact = false, className }: RatingDisplayProps) {
    const iconClass = compact ? 'h-2.5 w-2.5' : size === 'md' ? 'h-4 w-4' : 'h-3 w-3';
    const textClass = compact ? 'text-[10px]' : size === 'md' ? 'text-sm' : 'text-xs';

    if (reviewCount <= 0) {
        return (
            <span className={cn('inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 font-medium text-gray-500 sm:px-2', textClass, className)}>
                {compact ? 'No reviews' : 'No reviews yet'}
            </span>
        );
    }

    return (
        <span className={cn('inline-flex items-center gap-0.5 sm:gap-1', className)}>
            <span className="inline-flex">
                {Array.from({ length: 5 }).map((_, i) => (
                    <Star
                        key={i}
                        className={cn(
                            iconClass,
                            i < Math.round(rating) ? 'fill-amber-400 text-amber-400' : 'fill-gray-100 text-gray-200',
                        )}
                    />
                ))}
            </span>
            <span className={cn('font-medium text-amber-600', textClass)}>{Number(rating).toFixed(1)}</span>
            {!compact && reviewCount > 0 && <span className={cn('text-gray-400', textClass)}>({reviewCount})</span>}
        </span>
    );
}
