import { useEffect, useState } from 'react';

import { firstCityShopLinkIn, type CityShopDeepLink } from '@/lib/parse-chat-text';
import { cn } from '@/lib/utils';
import { formatPrice, productImageUrl } from '@/types/marketplace';

type PreviewProduct = {
    name: string;
    slug: string;
    price: number;
    imageUrl: string | null;
};

const productCache = new Map<string, PreviewProduct | null>();

async function fetchProductPreview(slug: string): Promise<PreviewProduct | null> {
    if (productCache.has(slug)) {
        return productCache.get(slug) ?? null;
    }

    try {
        const res = await fetch(`/api/v1/products/${encodeURIComponent(slug)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            productCache.set(slug, null);
            return null;
        }
        const json = (await res.json()) as {
            data?: {
                name?: string;
                slug?: string;
                effective_price?: number;
                price?: number;
                discount_price?: number | null;
                images?: Array<{ url?: string; path?: string; is_primary?: boolean }>;
            };
        };
        const data = json.data;
        if (!data?.name || !data.slug) {
            productCache.set(slug, null);
            return null;
        }
        const images = data.images ?? [];
        const primary = images.find((img) => img.is_primary) ?? images[0];
        const imageUrl = primary?.url
            ? primary.url
            : primary?.path
              ? productImageUrl(primary.path)
              : null;
        const price =
            typeof data.effective_price === 'number'
                ? data.effective_price
                : typeof data.discount_price === 'number'
                  ? data.discount_price
                  : Number(data.price) || 0;
        const preview: PreviewProduct = {
            name: data.name,
            slug: data.slug,
            price,
            imageUrl,
        };
        productCache.set(slug, preview);
        return preview;
    } catch {
        productCache.set(slug, null);
        return null;
    }
}

type ChatSharedLinkPreviewProps = {
    link: CityShopDeepLink;
    mine?: boolean;
    onOpen?: (path: string) => void;
};

export default function ChatSharedLinkPreview({ link, mine = false, onOpen }: ChatSharedLinkPreviewProps) {
    const [product, setProduct] = useState<PreviewProduct | null | undefined>(() =>
        link.kind === 'product' ? productCache.get(link.slug) : null,
    );

    useEffect(() => {
        if (link.kind !== 'product') {
            setProduct(null);
            return;
        }
        let cancelled = false;
        const cached = productCache.get(link.slug);
        if (cached !== undefined) {
            setProduct(cached);
            return;
        }
        setProduct(undefined);
        void fetchProductPreview(link.slug).then((result) => {
            if (!cancelled) setProduct(result);
        });
        return () => {
            cancelled = true;
        };
    }, [link.kind, link.slug]);

    if (link.kind !== 'product') {
        return (
            <button
                type="button"
                onClick={() => onOpen?.(link.path)}
                className={cn(
                    'mb-2 flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left',
                    mine ? 'bg-white/15' : 'bg-gray-50',
                )}
            >
                <div className="min-w-0 flex-1">
                    <p className={cn('text-sm font-semibold', mine ? 'text-white' : 'text-gray-900')}>
                        {link.kind === 'store' ? 'Open store' : 'Watch live'}
                    </p>
                    <p className={cn('text-[11px]', mine ? 'text-orange-100' : 'text-gray-400')}>cityunlock.net</p>
                </div>
                <span className={cn('text-lg', mine ? 'text-orange-100' : 'text-gray-400')}>›</span>
            </button>
        );
    }

    if (product === undefined) {
        return (
            <div
                className={cn(
                    'mb-2 h-16 w-full animate-pulse rounded-xl',
                    mine ? 'bg-white/10' : 'bg-gray-100',
                )}
            />
        );
    }

    if (!product) {
        return (
            <button
                type="button"
                onClick={() => onOpen?.(link.path)}
                className={cn(
                    'mb-2 w-full rounded-xl px-3 py-2.5 text-left',
                    mine ? 'bg-white/15' : 'bg-gray-50',
                )}
            >
                <p className={cn('text-sm font-semibold', mine ? 'text-white' : 'text-gray-900')}>Open product</p>
                <p className={cn('text-[11px]', mine ? 'text-orange-100' : 'text-gray-400')}>cityunlock.net</p>
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onOpen?.(link.path)}
            className={cn(
                'mb-2 w-full overflow-hidden rounded-xl text-left',
                mine ? 'bg-white/15' : 'bg-gray-50',
            )}
        >
            {product.imageUrl && (
                <img
                    src={product.imageUrl}
                    alt={product.name}
                    className="aspect-[16/10] w-full object-cover"
                    loading="lazy"
                />
            )}
            <div className="px-3 py-2">
                <p className={cn('line-clamp-2 text-sm font-extrabold', mine ? 'text-white' : 'text-gray-900')}>
                    {product.name}
                </p>
                <p className={cn('mt-0.5 text-xs font-extrabold', mine ? 'text-white' : 'text-orange-500')}>
                    {formatPrice(product.price)}
                </p>
                <p className={cn('mt-0.5 text-[11px]', mine ? 'text-orange-100' : 'text-gray-400')}>cityunlock.net</p>
            </div>
        </button>
    );
}

export function useFirstCityShopLink(text: string): CityShopDeepLink | null {
    return firstCityShopLinkIn(text);
}
