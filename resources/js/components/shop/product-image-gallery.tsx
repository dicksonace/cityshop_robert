import { ChevronLeft, ChevronRight, Film, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { ImageLightbox, type LightboxImage } from '@/components/shop/image-lightbox';
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
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const thumbsRef = useRef<HTMLDivElement>(null);

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

    const lightboxImages: LightboxImage[] = items
        .filter((item): item is Extract<GalleryItem, { type: 'image' }> => item.type === 'image')
        .map((item, index) => ({
            src: item.image.path,
            alt: `${productName} - image ${index + 1}`,
            label: `${productName} (${index + 1})`,
        }));

    const lightboxIndex = (() => {
        if (active?.type !== 'image') return 0;
        const idx = lightboxImages.findIndex((img) => img.src === active.image.path);
        return Math.max(0, idx);
    })();

    const goTo = useCallback(
        (index: number) => {
            if (total === 0) return;
            setCurrent(((index % total) + total) % total);
        },
        [total],
    );

    const prev = () => goTo(current - 1);
    const next = () => goTo(current + 1);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (lightboxOpen) return;
            if (e.key === 'ArrowLeft') goTo(current - 1);
            if (e.key === 'ArrowRight') goTo(current + 1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [current, goTo, lightboxOpen]);

    useEffect(() => {
        const container = thumbsRef.current;
        if (!container) return;
        const activeThumb = container.children[current] as HTMLElement | undefined;
        activeThumb?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }, [current]);

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
                    <button
                        type="button"
                        className="relative flex aspect-square w-full cursor-zoom-in items-center justify-center p-6"
                        onClick={() => setLightboxOpen(true)}
                        aria-label="Open full size image"
                    >
                        <img
                            src={productImageUrl(active?.type === 'image' ? active.image.path : undefined)}
                            alt={`${productName} - image ${current + 1}`}
                            className="max-h-full max-w-full object-contain transition-transform duration-500 group-hover:scale-105"
                        />
                        <div className="absolute bottom-3 right-3 rounded-full bg-black/40 p-2 text-white opacity-80 transition-opacity group-hover:opacity-100">
                            <ZoomIn className="h-4 w-4" />
                        </div>
                    </button>
                )}

                {total > 1 && (
                    <>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); prev(); }}
                            className="absolute left-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/95 p-2 shadow-md transition-all hover:scale-110 hover:bg-white sm:left-3"
                            aria-label="Previous media"
                        >
                            <ChevronLeft className="h-5 w-5 text-gray-700" />
                        </button>
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); next(); }}
                            className="absolute right-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/95 p-2 shadow-md transition-all hover:scale-110 hover:bg-white sm:right-3"
                            aria-label="Next media"
                        >
                            <ChevronRight className="h-5 w-5 text-gray-700" />
                        </button>

                        <span className="absolute top-3 right-3 z-10 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium text-white">
                            {current + 1} / {total}
                        </span>
                    </>
                )}
            </div>

            {total > 1 && (
                <div
                    ref={thumbsRef}
                    className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 scroll-smooth [scrollbar-width:thin]"
                >
                    {items.map((item, i) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => goTo(i)}
                            className={cn(
                                'relative h-16 w-16 shrink-0 scroll-mx-2 overflow-hidden rounded-xl border-2 transition-all',
                                i === current
                                    ? 'border-orange-500 ring-2 ring-orange-200'
                                    : 'border-gray-100 opacity-70 hover:opacity-100',
                            )}
                            aria-label={`View ${item.type} ${i + 1}`}
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
                                    className="h-full w-full bg-gray-50 object-contain p-1"
                                />
                            )}
                        </button>
                    ))}
                </div>
            )}

            <ImageLightbox
                images={lightboxImages}
                open={lightboxOpen}
                index={lightboxIndex}
                onClose={() => setLightboxOpen(false)}
                onIndexChange={(index) => {
                    const path = lightboxImages[index]?.src;
                    const galleryIndex = items.findIndex(
                        (item) => item.type === 'image' && item.image.path === path,
                    );
                    if (galleryIndex >= 0) setCurrent(galleryIndex);
                }}
            />
        </div>
    );
}
