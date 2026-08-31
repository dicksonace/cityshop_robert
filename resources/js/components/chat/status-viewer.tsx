import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import * as statusApi from '@/lib/status-api';
import { productImageUrl } from '@/types/marketplace';
import type { StatusBundle } from '@/types/status';

export default function StatusViewer({
    bundle,
    isMine,
    onClose,
}: {
    bundle: StatusBundle;
    isMine: boolean;
    onClose: () => void;
}) {
    const firstUnseen = bundle.items.findIndex((item) => !item.viewed);
    const [index, setIndex] = useState(firstUnseen >= 0 ? firstUnseen : 0);
    const item = bundle.items[index];

    useEffect(() => {
        if (!item || isMine || item.viewed) return;
        void statusApi.viewStatus(item.id).catch(() => undefined);
    }, [item, isMine]);

    if (!item || typeof document === 'undefined') return null;

    const media = item.media_url ? productImageUrl(item.media_url) : '';
    const bg = item.background_color || '#111827';

    const step = (delta: number) => {
        const next = index + delta;
        if (next < 0 || next >= bundle.items.length) {
            onClose();
            return;
        }
        setIndex(next);
    };

    return createPortal(
        <div className="fixed inset-0 z-[120] flex flex-col bg-black">
            <div className="flex gap-1 px-3 pt-3">
                {bundle.items.map((row, i) => (
                    <div
                        key={row.id}
                        className={`h-0.5 flex-1 rounded-full ${i <= index ? 'bg-white' : 'bg-white/30'}`}
                    />
                ))}
            </div>
            <div className="flex items-center gap-2 px-2 py-3 text-white">
                <button type="button" onClick={onClose} className="rounded-full p-1.5 hover:bg-white/10" aria-label="Close">
                    <X className="h-5 w-5" />
                </button>
                <div className="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-orange-500 text-xs font-bold">
                    {bundle.user.avatar ? (
                        <img src={productImageUrl(bundle.user.avatar)} alt="" className="h-full w-full object-cover" />
                    ) : (
                        (bundle.user.name?.[0] ?? '?').toUpperCase()
                    )}
                </div>
                <span className="text-sm font-semibold">{isMine ? 'My status' : bundle.user.name}</span>
                {isMine && (
                    <button
                        type="button"
                        className="ml-auto text-xs underline"
                        onClick={() => {
                            void statusApi.deleteStatus(item.id).then(onClose);
                        }}
                    >
                        Delete
                    </button>
                )}
            </div>
            <div className="relative min-h-0 flex-1" style={{ backgroundColor: item.type === 'text' ? bg : '#000' }}>
                <button
                    type="button"
                    className="absolute inset-y-0 left-0 z-10 w-1/3"
                    onClick={() => step(-1)}
                    aria-label="Previous"
                />
                <button
                    type="button"
                    className="absolute inset-y-0 right-0 z-10 w-1/3"
                    onClick={() => step(1)}
                    aria-label="Next"
                />
                {item.type === 'image' && media ? (
                    <img src={media} alt="" className="mx-auto h-full max-h-[70vh] object-contain" />
                ) : (
                    <p className="flex h-full items-center justify-center px-8 text-center text-2xl font-bold text-white">
                        {item.body}
                    </p>
                )}
                {item.type === 'image' && item.body?.trim() && (
                    <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-4 pb-6 pt-10 text-center text-sm font-medium text-white">
                        {item.body}
                    </div>
                )}
            </div>
        </div>,
        document.body,
    );
}
