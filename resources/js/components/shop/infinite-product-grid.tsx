import ProductCard from '@/components/shop/product-card';
import ProductCardSkeleton, { ProductGridSkeletons } from '@/components/shop/product-card-skeleton';
import { useInfiniteProducts } from '@/hooks/use-infinite-products';
import { cn } from '@/lib/utils';
import { Paginated, Product } from '@/types/marketplace';

interface InfiniteProductGridProps {
    initial: Paginated<Product>;
    resetKey: string;
    onAddToCart?: (productId: number) => void;
    variant?: 'grid' | 'list';
    gridClassName?: string;
    skeletonCount?: number;
}

export default function InfiniteProductGrid({
    initial,
    resetKey,
    onAddToCart,
    variant = 'grid',
    gridClassName,
    skeletonCount = 8,
}: InfiniteProductGridProps) {
    const { items, loading, hasMore, error, sentinelRef, retry } = useInfiniteProducts({
        initial,
        resetKey,
    });

    const listClass =
        variant === 'list'
            ? 'space-y-4'
            : cn('grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-3', gridClassName);

    return (
        <div>
            <div className={listClass}>
                {items.map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        onAddToCart={onAddToCart}
                        variant={variant}
                    />
                ))}
                {loading &&
                    Array.from({ length: Math.min(skeletonCount, 4) }).map((_, i) => (
                        <ProductCardSkeleton key={`sk-${i}`} variant={variant} />
                    ))}
            </div>

            {loading && variant === 'grid' && items.length === 0 && (
                <ProductGridSkeletons count={skeletonCount} variant={variant} className={gridClassName} />
            )}

            {error && (
                <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm text-amber-900">
                    <p>{error}</p>
                    <button
                        type="button"
                        onClick={() => void retry()}
                        className="mt-2 font-semibold text-orange-600 hover:underline"
                    >
                        Try again
                    </button>
                </div>
            )}

            {hasMore ? (
                <div ref={sentinelRef} className="h-8 w-full" aria-hidden />
            ) : (
                items.length > 0 && (
                    <p className="mt-8 text-center text-sm text-slate-400">You&apos;ve reached the end</p>
                )
            )}
        </div>
    );
}
