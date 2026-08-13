import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Radio, Store, Video } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

import JitsiLiveRoom from '@/components/live/jitsi-live-room';
import SellerLayout from '@/layouts/seller-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types';

interface LiveRoom {
    provider: string;
    domain: string;
    room_name: string;
}

interface LivestreamCard {
    id: number;
    title: string | null;
    store_name: string;
    store_slug: string;
    shop_photo?: string | null;
    room?: LiveRoom;
}

interface PageProps {
    livestream: LivestreamCard | null;
    storeUrl: string | null;
}

export default function SellerLivestream({ livestream, storeUrl }: PageProps) {
    const { auth, flash } = usePage<SharedData>().props;
    const form = useForm({ title: livestream?.title ?? '' });
    const [hostReady, setHostReady] = useState(false);

    const start = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('seller.livestream.start'));
    };

    const endLive = useCallback(() => {
        router.post(route('seller.livestream.end'));
    }, []);

    useEffect(() => {
        setHostReady(false);
    }, [livestream?.id]);

    useEffect(() => {
        if (!livestream || !hostReady) return;

        const ping = () => {
            void fetch(route('seller.livestream.heartbeat'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                credentials: 'same-origin',
            }).then(async (res) => {
                try {
                    const json = (await res.json()) as { ok?: boolean };
                    if (json.ok === false) {
                        router.reload({ only: ['livestream'] });
                    }
                } catch {
                    // ignore parse errors
                }
            });
        };

        ping();
        const tick = window.setInterval(ping, 25000);
        return () => window.clearInterval(tick);
    }, [livestream?.id, hostReady]);

    return (
        <SellerLayout title="Go Live" active="livestream">
            <Head title="Go Live" />

            {flash?.success && (
                <div className="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            {livestream?.room ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-100 bg-white p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <span className="relative flex h-3 w-3">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-70" />
                                <span className="relative inline-flex h-3 w-3 rounded-full bg-red-500" />
                            </span>
                            <div>
                                <p className="font-bold text-gray-900">{livestream.title || 'Live'}</p>
                                <p className="text-sm text-gray-500">Buyers can watch on your store page.</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {storeUrl && (
                                <a
                                    href={storeUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    <Store className="h-4 w-4" />
                                    View store
                                </a>
                            )}
                            <Button type="button" variant="destructive" onClick={endLive}>
                                End live
                            </Button>
                        </div>
                    </div>
                    <JitsiLiveRoom
                        room={livestream.room}
                        displayName={livestream.store_name || auth.user?.name || 'Store'}
                        avatarUrl={livestream.shop_photo}
                        isHost
                        onJoined={() => setHostReady(true)}
                    />
                    <p className="text-xs text-gray-500">
                        Allow camera and microphone. Shoppers join after your camera connects. Keep this page open while
                        you are live, and tap End live when you are done.
                    </p>
                </div>
            ) : (
                <div className="mx-auto max-w-lg rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <Video className="h-6 w-6" />
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">Go live from your store</h1>
                    <p className="mt-2 text-sm text-gray-500">
                        Start a live video. Shoppers on CityShop will see a LIVE badge on your store and can watch you sell.
                    </p>
                    <form className="mt-6 space-y-4" onSubmit={start}>
                        <div>
                            <Label htmlFor="title">Live title (optional)</Label>
                            <Input
                                id="title"
                                className="mt-1"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="Evening deals, new stock…"
                            />
                        </div>
                        <Button type="submit" disabled={form.processing} className="w-full bg-red-600 hover:bg-red-700">
                            <Radio className="mr-2 h-4 w-4" />
                            {form.processing ? 'Starting…' : 'Go Live'}
                        </Button>
                    </form>
                    <p className="mt-4 text-center text-xs text-gray-400">
                        Use your phone browser if you want to go live with the phone camera.
                    </p>
                    <p className="mt-2 text-center text-sm">
                        <Link href={route('seller.dashboard')} className="font-medium text-orange-600 hover:underline">
                            Back to dashboard
                        </Link>
                    </p>
                </div>
            )}
        </SellerLayout>
    );
}
