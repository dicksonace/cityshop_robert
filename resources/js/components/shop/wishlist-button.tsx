import { router, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { MouseEvent, useEffect, useState } from 'react';

import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

interface WishlistButtonProps {
    productId: number;
    className?: string;
    size?: 'sm' | 'md';
    /** Called optimistically before the server responds (adding = true when liking). */
    onOptimisticToggle?: (adding: boolean) => void;
}

export default function WishlistButton({
    productId,
    className,
    size = 'sm',
    onOptimisticToggle,
}: WishlistButtonProps) {
    const { auth, wishlistProductIds = [] } = usePage<SharedData>().props;
    const serverWishlisted = (wishlistProductIds as number[]).includes(productId);
    const [optimisticWishlisted, setOptimisticWishlisted] = useState<boolean | null>(null);
    const isWishlisted = optimisticWishlisted ?? serverWishlisted;

    useEffect(() => {
        setOptimisticWishlisted(null);
    }, [serverWishlisted]);

    const toggle = (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!auth.user) {
            router.visit(route('login'));
            return;
        }

        const adding = !isWishlisted;
        setOptimisticWishlisted(adding);
        onOptimisticToggle?.(adding);

        router.post(
            route('wishlist.toggle'),
            { product_id: productId },
            {
                preserveScroll: true,
                onError: () => {
                    setOptimisticWishlisted(!adding);
                    onOptimisticToggle?.(!adding);
                },
            },
        );
    };

    const sizeClass = size === 'md' ? 'h-10 w-10' : 'h-8 w-8';
    const iconSize = size === 'md' ? 'h-5 w-5' : 'h-4 w-4';

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={isWishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
            aria-pressed={isWishlisted}
            className={cn(
                'flex items-center justify-center rounded-lg border transition-colors',
                sizeClass,
                isWishlisted
                    ? 'border-red-200 bg-red-50 text-red-500'
                    : 'border-gray-100 text-gray-400 hover:border-red-200 hover:text-red-400',
                className,
            )}
        >
            <Heart className={cn(iconSize, isWishlisted && 'fill-current')} />
        </button>
    );
}
