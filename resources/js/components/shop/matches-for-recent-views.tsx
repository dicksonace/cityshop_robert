import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

import { getRecentViewIds } from '@/lib/recent-views';
import { formatPrice, productImageUrl } from '@/types/marketplace';

type MatchProduct = {
    id: number;
    name: string;
    slug: string;
    price: number;
    discount_price: number | null;
    images: { path: string }[];
    category_id: number | null;
    sellers_in_category: number;
};

export default function MatchesForRecentViews() {
    const [products, setProducts] = useState<MatchProduct[]>([]);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        const ids = getRecentViewIds();
        if (ids.length === 0) {
            setLoaded(true);
            return;
        }

        let cancelled = false;

        fetch(route('home.matches-for-recent-views', { ids: ids.join(',') }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) return { products: [] as MatchProduct[] };
                return (await res.json()) as { products?: MatchProduct[] };
            })
            .then((data) => {
                if (!cancelled) {
                    setProducts(Array.isArray(data.products) ? data.products : []);
                }
            })
            .catch(() => {
                if (!cancelled) setProducts([]);
            })
            .finally(() => {
                if (!cancelled) setLoaded(true);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    if (!loaded || products.length === 0) {
        return null;
    }

    return (
        <section className="mb-4 rounded-xl border border-gray-100 bg-white p-3 shadow-sm sm:rounded-2xl sm:p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold text-gray-900 sm:text-base">Matches for recent views</h2>
                <ChevronRight className="h-4 w-4 shrink-0 text-gray-400" aria-hidden />
            </div>

            <div className="flex gap-3 overflow-x-auto overscroll-x-contain pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                {products.map((product) => {
                    const price = product.discount_price ?? product.price;
                    const sellers = Math.max(1, product.sellers_in_category || 1);
                    const image = product.images?.[0]?.path;

                    return (
                        <Link
                            key={product.id}
                            href={route('products.show', product.slug)}
                            className="w-[7.5rem] shrink-0 sm:w-36"
                        >
                            <div className="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-slate-50 to-orange-50/40 p-2">
                                <img
                                    src={productImageUrl(image)}
                                    alt={product.name}
                                    className="max-h-full max-w-full object-contain"
                                    loading="lazy"
                                />
                            </div>
                            <p className="mt-1.5 truncate text-[11px] text-gray-500 sm:text-xs">
                                {sellers} {sellers === 1 ? 'seller' : 'sellers'}
                            </p>
                            <p className="truncate text-xs font-semibold text-gray-900 sm:text-sm">
                                From {formatPrice(price)}
                            </p>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
