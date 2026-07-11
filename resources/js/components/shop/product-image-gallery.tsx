import { ChevronLeft, ChevronRight, Film, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { cn } from '@/lib/utils';
import { ProductImage, productImageUrl, productVideoUrl } from '@/types/marketplace';

interface ProductImageGalleryProps {
    images: ProductImage[];
    productName: string;
    videoPath?: string | null;
    videoDuration?: number | null;
    className?: string;
}

type GalleryItem =
    | { type: 'image'; image: ProductImage; key: string }
    | { type: 'video'; path: string; key: string };

export default function ProductImageGallery({
    images,
    productName,
    videoPath,
    videoDuration,
    className,
}: ProductImageGalleryProps) {
    const [current, setCurrent] = useState(0);
    const [zoomed, setZoomed] = useState(false);

    const sortedImages = [...images].sort((a, b) => {
        if (a.is_primary && !b.is_primary) return -1;
        if (!a.is_primary && b.is_primary) return 1;
        return 0;
    });

    const items: GalleryItem[] = [
        ...sortedImages.map((image, index) => ({
            type: 'image' as const,
            image,
            key: `image-${image.id ?? index}`,
        })),
        ...(videoPath
            ? [{ type: 'video' as const, path: videoPath, key: `video-${videoPath}` }]
            : []),
    ];

    const total = items.length;
    const active = items[current];

    const goTo = useCallback(
        (index: number) => {
            if (total === 0) return;
            setCurrent(((index % total) + total) % total);
            setZoomed(false);
        },
        [total],
    );

    const prev = () => goTo(current - 1);
    const next = () => goTo(current + 1);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'ArrowLeft') goTo(current - 1);
            if (e.key === 'ArrowRight') goTo(current + 1);
            if (e.key === 'Escape') setZoomed(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [current, goTo]);

    if (total === 0) {
        return (
            <div className={cn('flex aspect-square items-center justify-center rounded-2xl bg-gray-100', className)}>
                <p className="text-sm text-gray-400">No images</p>
            </div>
        );
    }

    return (
        <div className={cn('space-y-3', className)}>
            <div className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-50 to-white shadow-sm ring-1 ring-gray-100">
                {active?.type === 'video' ? (
                    <div className="relative aspect-square bg-black">
                        <video
                            key={active.path}
                            src={productVideoUrl(active.path)}
                            controls
                            playsInline
                            preload="metadata"
                            className="h-full w-full object-contain"
                        >
                            Your browser does not support product videos.
                        </video>
                        <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-orange-500 px-2.5 py-1 text-xs font-semibold text-white">
                            <Film className="h-3.5 w-3.5" />
                            Video
                            {videoDuration ? ` · ${Math.floor(videoDuration / 60)}:${String(videoDuration % 60).padStart(2, '0')}` : ''}
                        </span>
                    </div>
                ) : (
                    <div
                        className={cn(
                            'relative flex aspect-square items-center justify-center p-6 transition-transform duration-300',
                            zoomed && 'cursor-zoom-out',
                        )}
                        onClick={() => setZoomed(!zoomed)}
                    >
                        <img
                            src={productImageUrl(active?.type === 'image' ? active.image.path : undefined)}
                            alt={`${productName} - image ${current + 1}`}
                            className={cn(
                                'max-h-full max-w-full object-contain transition-transform duration-500',
                                zoomed ? 'scale-150' : 'group-hover:scale-105',
                            )}
                        />
                        {!zoomed && (
                            <div className="absolute bottom-3 right-3 rounded-full bg-black/40 p-2 text-white opacity-0 transition-opacity group-hover:opacity-100">
                                <ZoomIn className="h-4 w-4" />
                            </div>
                        )}
                    </div>
                )}

                {total > 1 && (
                    <>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); prev(); }}
                            className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow-md transition-all hover:bg-white hover:scale-110"
                            aria-label="Previous media"
                        >
                            <ChevronLeft className="h-5 w-5 text-gray-700" />
                        </button>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); next(); }}
                            className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow-md transition-all hover:bg-white hover:scale-110"
                            aria-label="Next media"
                        >
                            <ChevronRight className="h-5 w-5 text-gray-700" />
                        </button>

                        <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                            {items.map((item, i) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={(e) => { e.stopPropagation(); goTo(i); }}
                                    className={cn(
                                        'h-2 rounded-full transition-all',
                                        i === current ? 'w-6 bg-orange-500' : 'w-2 bg-white/70 hover:bg-white',
                                    )}
                                    aria-label={`Go to ${item.type} ${i + 1}`}
                                />
                            ))}
                        </div>

                        <span className="absolute top-3 right-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium text-white">
                            {current + 1} / {total}
                        </span>
                    </>
                )}
            </div>

            {total > 1 && (
                <div className="flex gap-2 overflow-x-auto pb-1">
                    {items.map((item, i) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => goTo(i)}
                            className={cn(
                                'relative h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 transition-all',
                                i === current
                                    ? 'border-orange-500 ring-2 ring-orange-200'
                                    : 'border-gray-100 opacity-70 hover:opacity-100',
                            )}
                        >
                            {item.type === 'video' ? (
                                <div className="flex h-full w-full flex-col items-center justify-center bg-gray-900 text-white">
                                    <Film className="h-5 w-5 text-orange-400" />
                                    <span className="mt-0.5 text-[10px] font-semibold">Video</span>
                                </div>
                            ) : (
                                <img
                                    src={productImageUrl(item.image.path)}
                                    alt=""
                                    className="h-full w-full object-contain bg-gray-50 p-1"
                                />
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
