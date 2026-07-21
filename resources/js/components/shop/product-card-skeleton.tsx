import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface ProductCardSkeletonProps {
    variant?: 'grid' | 'list';
    className?: string;
}

export default function ProductCardSkeleton({ variant = 'grid', className }: ProductCardSkeletonProps) {
    if (variant === 'list') {
        return (
            <div className={cn('flex gap-3 rounded-2xl border border-gray-100 bg-white p-3 sm:gap-4 sm:p-4', className)}>
                <Skeleton className="h-28 w-28 shrink-0 rounded-xl sm:h-36 sm:w-36" />
                <div className="flex min-w-0 flex-1 flex-col justify-between py-1">
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-[80%] max-w-[16rem]" />
                        <Skeleton className="h-3 w-24" />
                        <Skeleton className="h-3 w-32" />
                    </div>
                    <div className="mt-3 flex items-center justify-between">
                        <Skeleton className="h-5 w-20" />
                        <Skeleton className="h-8 w-20 rounded-lg" />
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className={cn('overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm', className)}>
            <Skeleton className="aspect-square w-full rounded-none" />
            <div className="space-y-2 p-2.5 sm:p-4">
                <Skeleton className="h-2.5 w-16" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-3 w-20" />
                <div className="flex items-end justify-between pt-2">
                    <Skeleton className="h-5 w-16" />
                    <Skeleton className="h-7 w-7 rounded-lg sm:h-8 sm:w-8" />
                </div>
            </div>
        </div>
    );
}

export function ProductGridSkeletons({
    count = 8,
    variant = 'grid',
    className,
}: {
    count?: number;
    variant?: 'grid' | 'list';
    className?: string;
}) {
    return (
        <div
            className={cn(
                variant === 'list'
                    ? 'space-y-4'
                    : 'grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4',
                className,
            )}
            aria-hidden
        >
            {Array.from({ length: count }).map((_, i) => (
                <ProductCardSkeleton key={i} variant={variant} />
            ))}
        </div>
    );
}
