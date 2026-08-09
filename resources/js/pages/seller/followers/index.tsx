import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { Paginated } from '@/types/marketplace';

interface FollowerRow {
    id: number;
    followed_at?: string | null;
    user?: {
        id: number;
        name: string;
        mobile?: string | null;
        avatar?: string | null;
        role?: string | null;
    } | null;
}

interface FollowersIndexProps {
    followers: Paginated<FollowerRow>;
    total: number;
}

export default function SellerFollowersIndex({ followers, total }: FollowersIndexProps) {
    return (
        <SellerLayout title="Followers" active="followers">
            <Head title="Followers" />

            <div className="mb-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div className="flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                        <Users className="h-5 w-5" />
                    </div>
                    <div>
                        <p className="text-sm text-gray-500">People following your store</p>
                        <p className="text-2xl font-bold text-gray-900">{total}</p>
                    </div>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                {followers.data.length === 0 ? (
                    <p className="p-12 text-center text-gray-500">
                        No followers yet. Shoppers can follow you from your products and store page.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-50">
                        {followers.data.map((row) => {
                            const name = row.user?.name ?? 'CityShop user';
                            const letter = name.trim().charAt(0).toUpperCase() || 'U';
                            const when = row.followed_at
                                ? new Date(row.followed_at).toLocaleDateString()
                                : null;

                            return (
                                <li key={row.id} className="flex items-center gap-3 px-4 py-3.5">
                                    {row.user?.avatar ? (
                                        <img
                                            src={row.user.avatar}
                                            alt=""
                                            className="h-11 w-11 rounded-full object-cover"
                                        />
                                    ) : (
                                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-700">
                                            {letter}
                                        </span>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-gray-900">{name}</p>
                                        <p className="truncate text-xs text-gray-500">
                                            {[row.user?.mobile, when ? `Followed ${when}` : null]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {(followers.last_page > 1) && (
                <div className="mt-4 flex justify-center gap-3">
                    {followers.current_page > 1 && (
                        <Link
                            href={route('seller.followers.index', { page: followers.current_page - 1 })}
                            className="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700"
                        >
                            Previous
                        </Link>
                    )}
                    {followers.current_page < followers.last_page && (
                        <Link
                            href={route('seller.followers.index', { page: followers.current_page + 1 })}
                            className="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700"
                        >
                            Next
                        </Link>
                    )}
                </div>
            )}
        </SellerLayout>
    );
}
