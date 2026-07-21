import { Eye, Heart } from 'lucide-react';

import { cn } from '@/lib/utils';
import { formatCompactCount } from '@/types/marketplace';

interface ProductEngagementStatsProps {
    views?: number | null;
    likes?: number | null;
    className?: string;
    size?: 'sm' | 'md';
}

export default function ProductEngagementStats({
    views = 0,
    likes = 0,
    className,
    size = 'sm',
}: ProductEngagementStatsProps) {
    const viewCount = Math.max(0, Number(views) || 0);
    const likeCount = Math.max(0, Number(likes) || 0);
    const iconClass = size === 'md' ? 'h-4 w-4' : 'h-3 w-3';
    const textClass = size === 'md' ? 'text-sm' : 'text-[10px] sm:text-xs';

    return (
        <div
            className={cn('flex items-center gap-2.5 text-gray-500', textClass, className)}
            aria-label={`${formatCompactCount(viewCount)} views, ${formatCompactCount(likeCount)} likes`}
        >
            <span className="inline-flex items-center gap-1" title={`${viewCount.toLocaleString()} views`}>
                <Eye className={cn(iconClass, 'text-gray-400')} aria-hidden />
                <span className="font-medium tabular-nums text-gray-600">{formatCompactCount(viewCount)}</span>
                {size === 'md' && <span className="text-gray-400">views</span>}
            </span>
            <span className="h-3 w-px bg-gray-200" aria-hidden />
            <span className="inline-flex items-center gap-1" title={`${likeCount.toLocaleString()} likes`}>
                <Heart className={cn(iconClass, 'text-rose-400')} aria-hidden />
                <span className="font-medium tabular-nums text-gray-600">{formatCompactCount(likeCount)}</span>
                {size === 'md' && <span className="text-gray-400">likes</span>}
            </span>
        </div>
    );
}
