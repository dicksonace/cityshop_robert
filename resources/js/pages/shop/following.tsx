import { Head, Link, router, usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { productImageUrl } from '@/types/marketplace';

interface FollowedSeller {
    id: number;
    name: string;
    store_name?: string | null;
    store_slug?: string | null;
    shop_photo?: string | null;
    rating?: number | null;
    total_sales?: number | null;
    follower_count?: number | null;
}

interface FollowingRow {
    id: number;
    followed_at?: string | null;
    seller?: FollowedSeller | null;
}

interface FollowingProps {
    following: FollowingRow[];
}

function formatMeta(seller: FollowedSeller): string {
    const parts: string[] = [];
    if (seller.rating != null) {
        parts.push(`${seller.rating.toFixed(1)}★`);
    }
    if (seller.total_sales != null) {
        parts.push(`${seller.total_sales} sales`);
    }
    if (seller.follower_count != null) {
        parts.push(
            `${seller.follower_count} ${seller.follower_count === 1 ? 'follower' : 'followers'}`,
        );
    }
    return parts.join(' · ');
}

export default function BuyerFollowing({ following }: FollowingProps) {
    const { flash } = usePage<SharedData>().props;

    const unfollow = (sellerId: number) => {
        router.post(
            route('following.toggle'),
            { seller_id: sellerId },
            { preserveScroll: true },
        );
    };

    return (
        <ShopLayout>
            <Head title="Following" />
            <div className="mx-auto max-w-lg px-3 py-4 sm:px-4 sm:py-8">
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                        <Store className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Following</h1>
                        <p className="text-sm text-gray-500">
                            {following.length}{' '}
                            {following.length === 1 ? 'seller' : 'sellers'} you follow
                        </p>
                    </div>
                </div>

                {(flash?.success || flash?.error) && (
                    <div
                        className={`mb-3 rounded-xl px-3 py-2 text-sm ring-1 ${
                            flash.success
                                ? 'bg-green-50 text-green-700 ring-green-100'
                                : 'bg-red-50 text-red-700 ring-red-100'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                {following.length === 0 ? (
                    <div className="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
                        <Store className="mx-auto h-12 w-12 text-gray-200" />
                        <p className="mt-4 text-sm text-gray-500">
                            You are not following any sellers yet. Open a product and tap Follow this
                            seller.
                        </p>
                        <Link href={route('home')} className="mt-4 inline-block text-orange-500 hover:underline">
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {following.map((row) => {
                            const seller = row.seller;
                            if (!seller) return null;
                            const name = seller.store_name || seller.name || 'Store';
                            const letter = name.trim().charAt(0).toUpperCase() || 'S';
                            const photo = seller.shop_photo
                                ? productImageUrl(seller.shop_photo)
                                : null;
                            const meta = formatMeta(seller);
                            const storeHref = seller.store_slug
                                ? route('store.show', seller.store_slug)
                                : null;

                            return (
                                <li
                                    key={row.id}
                                    className="rounded-2xl bg-white p-3.5 shadow-sm ring-1 ring-gray-100"
                                >
                                    <div className="flex items-center gap-3">
                                        {storeHref ? (
                                            <Link href={storeHref} className="shrink-0">
                                                {photo ? (
                                                    <img
                                                        src={photo}
                                                        alt=""
                                                        className="h-12 w-12 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-700">
                                                        {letter}
                                                    </span>
                                                )}
                                            </Link>
                                        ) : photo ? (
                                            <img
                                                src={photo}
                                                alt=""
                                                className="h-12 w-12 shrink-0 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-700">
                                                {letter}
                                            </span>
                                        )}

                                        <div className="min-w-0 flex-1">
                                            {storeHref ? (
                                                <Link
                                                    href={storeHref}
                                                    className="block truncate font-semibold text-gray-900 hover:text-orange-600"
                                                >
                                                    {name}
                                                </Link>
                                            ) : (
                                                <p className="truncate font-semibold text-gray-900">{name}</p>
                                            )}
                                            {meta ? (
                                                <p className="truncate text-xs text-gray-500">{meta}</p>
                                            ) : null}
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => unfollow(seller.id)}
                                            className="shrink-0 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Unfollow
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </ShopLayout>
    );
}
