import { Head, useForm } from '@inertiajs/react';
import { MessageSquare, Star } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { Button } from '@/components/ui/button';
import SellerLayout from '@/layouts/seller-layout';
import { Paginated, productImageUrl } from '@/types/marketplace';

interface ReviewRow {
    id: number;
    rating: number;
    comment?: string;
    seller_reply?: string | null;
    seller_replied_at?: string | null;
    created_at: string;
    user?: { name: string };
    product?: { id: number; name: string; slug: string; images?: { path: string }[] };
}

interface ReviewsIndexProps {
    reviews: Paginated<ReviewRow>;
    stats: { total: number; unreplied: number; average: number };
}

export default function SellerReviewsIndex({ reviews, stats }: ReviewsIndexProps) {
    const [replyId, setReplyId] = useState<number | null>(null);
    const replyForm = useForm({ seller_reply: '' });

    const submitReply: FormEventHandler = (e) => {
        e.preventDefault();
        if (!replyId) return;
        replyForm.post(route('seller.reviews.reply', replyId), {
            onSuccess: () => { setReplyId(null); replyForm.reset(); },
        });
    };

    return (
        <SellerLayout title="Reviews" active="reviews">
            <Head title="Reviews" />

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Total reviews', value: stats.total },
                    { label: 'Awaiting reply', value: stats.unreplied },
                    { label: 'Average rating', value: `${stats.average}★` },
                ].map((s) => (
                    <div key={s.label} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p className="text-sm text-gray-500">{s.label}</p>
                        <p className="mt-1 text-2xl font-bold text-gray-900">{s.value}</p>
                    </div>
                ))}
            </div>

            <div className="rounded-2xl border border-gray-100 bg-white shadow-sm">
                {reviews.data.length === 0 ? (
                    <p className="p-12 text-center text-gray-500">No reviews yet.</p>
                ) : (
                    <div className="divide-y">
                        {reviews.data.map((review) => (
                            <article key={review.id} className="p-6">
                                <div className="flex gap-4">
                                    {review.product?.images?.[0] && (
                                        <img src={productImageUrl(review.product.images[0].path)} alt="" className="h-14 w-14 rounded-lg object-contain bg-gray-50 p-1" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold text-gray-900">{review.product?.name}</p>
                                        <p className="text-sm text-gray-500">{review.user?.name} · {new Date(review.created_at).toLocaleDateString()}</p>
                                        <div className="mt-1 flex gap-0.5">
                                            {Array.from({ length: 5 }).map((_, i) => (
                                                <Star key={i} className={`h-4 w-4 ${i < review.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'}`} />
                                            ))}
                                        </div>
                                        {review.comment && <p className="mt-2 text-sm text-gray-600">{review.comment}</p>}

                                        {review.seller_reply ? (
                                            <div className="mt-3 rounded-lg bg-orange-50 p-3 text-sm">
                                                <p className="font-medium text-orange-900">Your reply</p>
                                                <p className="mt-1 text-orange-800">{review.seller_reply}</p>
                                            </div>
                                        ) : replyId === review.id ? (
                                            <form onSubmit={submitReply} className="mt-3 space-y-2">
                                                <textarea
                                                    value={replyForm.data.seller_reply}
                                                    onChange={(e) => replyForm.setData('seller_reply', e.target.value)}
                                                    className="w-full rounded-lg border px-3 py-2 text-sm"
                                                    rows={3}
                                                    placeholder="Write a professional reply..."
                                                    required
                                                />
                                                <div className="flex gap-2">
                                                    <Button type="submit" size="sm" disabled={replyForm.processing} className="bg-orange-500">Post reply</Button>
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setReplyId(null)}>Cancel</Button>
                                                </div>
                                            </form>
                                        ) : (
                                            <Button type="button" size="sm" variant="outline" className="mt-3" onClick={() => setReplyId(review.id)}>
                                                <MessageSquare className="mr-1 h-4 w-4" /> Reply
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </SellerLayout>
    );
}
