import { Head, Link } from '@inertiajs/react';
import { MessageSquare, Star } from 'lucide-react';

import PanelLayout from '@/layouts/panel-layout';
import { Paginated, ProductReview } from '@/types/marketplace';

interface ProductReviewsProps {
    product: { id: number; name: string; slug: string; rating: number; review_count: number };
    reviews: Paginated<ProductReview & { order?: { order_number: string } }>;
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index'), active: true },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

function StarRating({ rating }: { rating: number }) {
    return (
        <span className="inline-flex gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <Star key={n} className={`h-4 w-4 ${n <= rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'}`} />
            ))}
        </span>
    );
}

export default function SellerProductReviews({ product, reviews }: ProductReviewsProps) {
    return (
        <PanelLayout title="Product Comments" nav={nav}>
            <Head title={`Comments — ${product.name}`} />

            <Link href={route('seller.products.index')} className="text-sm text-orange-500 hover:underline">
                ← Back to Products
            </Link>

            <div className="mt-4 rounded-xl bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">{product.name}</h1>
                        <div className="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <StarRating rating={Math.round(Number(product.rating))} />
                            <span>{Number(product.rating).toFixed(1)} · {product.review_count} review{product.review_count !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <Link
                        href={route('products.show', product.slug)}
                        target="_blank"
                        className="text-sm text-orange-500 hover:underline"
                    >
                        View on shop →
                    </Link>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="flex items-center gap-2 border-b bg-gray-50 px-4 py-3">
                    <MessageSquare className="h-4 w-4 text-orange-500" />
                    <h2 className="font-semibold text-gray-900">Customer Comments</h2>
                </div>

                {reviews.data.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">No comments yet for this product.</p>
                ) : (
                    <div className="divide-y">
                        {reviews.data.map((review) => (
                            <article key={review.id} className="px-4 py-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium text-gray-900">{review.user?.name ?? 'Customer'}</p>
                                        <div className="mt-1 flex items-center gap-2">
                                            <StarRating rating={review.rating} />
                                            {review.created_at && (
                                                <span className="text-xs text-gray-400">
                                                    {new Date(review.created_at).toLocaleDateString('en-GH', {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    })}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    {review.order?.order_number && (
                                        <span className="text-xs text-gray-400">Order {review.order.order_number}</span>
                                    )}
                                </div>
                                {review.comment && (
                                    <p className="mt-2 text-sm leading-relaxed text-gray-600">{review.comment}</p>
                                )}
                            </article>
                        ))}
                    </div>
                )}

                {reviews.last_page > 1 && (
                    <div className="flex flex-wrap gap-2 border-t p-4">
                        {reviews.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className={`rounded-md px-3 py-1.5 text-sm ${
                                        link.active ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : null,
                        )}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
