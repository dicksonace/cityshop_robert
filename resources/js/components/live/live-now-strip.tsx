import { Link } from '@inertiajs/react';
import { Radio } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface LiveNowCard {
    id: number;
    title?: string | null;
    store_name: string;
    store_slug: string;
    shop_photo?: string | null;
}

export default function LiveNowStrip({ lives }: { lives: LiveNowCard[] }) {
    const [items, setItems] = useState<LiveNowCard[]>(lives);

    useEffect(() => {
        setItems(lives);
    }, [lives]);

    useEffect(() => {
        let cancelled = false;

        const refresh = async () => {
            try {
                const res = await fetch('/api/v1/livestreams', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok || cancelled) return;
                const json = (await res.json()) as { data?: LiveNowCard[] };
                if (cancelled) return;
                setItems(Array.isArray(json.data) ? json.data : []);
            } catch {
                // keep current strip
            }
        };

        const tick = window.setInterval(() => {
            void refresh();
        }, 12000);

        // If SSR said something is live, re-check soon; also poll when empty so a new live can appear.
        void refresh();

        return () => {
            cancelled = true;
            window.clearInterval(tick);
        };
    }, []);

    if (!items.length) return null;

    return (
        <section className="border-b border-red-50 bg-white">
            <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6">
                <div className="mb-3 flex items-center gap-2">
                    <span className="relative flex h-2.5 w-2.5">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-70" />
                        <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500" />
                    </span>
                    <h2 className="text-sm font-bold uppercase tracking-wide text-gray-900">Live now</h2>
                </div>
                <div className="flex gap-3 overflow-x-auto pb-1">
                    {items.map((live) => (
                        <Link
                            key={live.id}
                            href={route('store.show', live.store_slug)}
                            className="w-36 shrink-0 overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div className="relative h-24 bg-gray-100">
                                {live.shop_photo ? (
                                    <img src={live.shop_photo} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-2xl font-bold text-gray-300">
                                        {live.store_name.charAt(0)}
                                    </div>
                                )}
                                <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                    <Radio className="h-3 w-3" />
                                    LIVE
                                </span>
                            </div>
                            <div className="p-2.5">
                                <p className="truncate text-sm font-semibold text-gray-900">{live.store_name}</p>
                                <p className="truncate text-xs text-gray-500">{live.title || 'Live from the store'}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
