import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';
import { productImageUrl } from '@/types/marketplace';

export type LightboxImage = {
    src: string;
    alt?: string;
    label?: string;
};

type ImageLightboxProps = {
    images: LightboxImage[];
    open: boolean;
    index?: number;
    onClose: () => void;
    onIndexChange?: (index: number) => void;
};

export function ImageLightbox({
    images,
    open,
    index = 0,
    onClose,
    onIndexChange,
}: ImageLightboxProps) {
    const total = images.length;
    const safeIndex = total === 0 ? 0 : ((index % total) + total) % total;
    const current = images[safeIndex];

    const goTo = useCallback(
        (next: number) => {
            if (total === 0) return;
            const resolved = ((next % total) + total) % total;
            onIndexChange?.(resolved);
        },
        [onIndexChange, total],
    );

    useEffect(() => {
        if (!open) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowLeft') goTo(safeIndex - 1);
            if (e.key === 'ArrowRight') goTo(safeIndex + 1);
        };

        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onKey);
        };
    }, [open, onClose, goTo, safeIndex]);

    if (!open || total === 0 || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            className="fixed inset-0 z-[100] flex flex-col bg-black/92 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
            aria-label="Image viewer"
            onClick={onClose}
        >
            <div className="flex items-center justify-between gap-3 px-4 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <button
                    type="button"
                    onClick={onClose}
                    className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="Close"
                >
                    <X className="h-5 w-5" />
                </button>
                <div className="min-w-0 text-center">
                    {current?.label && (
                        <p className="truncate text-sm font-medium text-white/90">{current.label}</p>
                    )}
                    <p className="text-xs text-white/60">
                        {safeIndex + 1} / {total}
                    </p>
                </div>
                <div className="h-10 w-10" aria-hidden />
            </div>

            <div
                className="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-4"
                onClick={(e) => e.stopPropagation()}
            >
                {total > 1 && (
                    <>
                        <button
                            type="button"
                            onClick={() => goTo(safeIndex - 1)}
                            className="absolute left-2 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white shadow-lg backdrop-blur-md transition hover:bg-white/25 sm:left-6"
                            aria-label="Previous image"
                        >
                            <ChevronLeft className="h-6 w-6" />
                        </button>
                        <button
                            type="button"
                            onClick={() => goTo(safeIndex + 1)}
                            className="absolute right-2 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white shadow-lg backdrop-blur-md transition hover:bg-white/25 sm:right-6"
                            aria-label="Next image"
                        >
                            <ChevronRight className="h-6 w-6" />
                        </button>
                    </>
                )}

                <img
                    key={current.src}
                    src={productImageUrl(current.src)}
                    alt={current.alt ?? current.label ?? 'Full size image'}
                    className="max-h-full max-w-full rounded-lg object-contain shadow-2xl animate-in zoom-in-95 fade-in duration-200"
                    draggable={false}
                />
            </div>

            {total > 1 && (
                <div
                    className="flex justify-center gap-2 overflow-x-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-1"
                    onClick={(e) => e.stopPropagation()}
                >
                    {images.map((image, i) => (
                        <button
                            key={`${image.src}-${i}`}
                            type="button"
                            onClick={() => goTo(i)}
                            className={cn(
                                'h-14 w-14 shrink-0 overflow-hidden rounded-lg border-2 transition',
                                i === safeIndex
                                    ? 'border-orange-500 ring-2 ring-orange-400/40'
                                    : 'border-white/20 opacity-70 hover:opacity-100',
                            )}
                            aria-label={`View image ${i + 1}`}
                        >
                            <img
                                src={productImageUrl(image.src)}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>,
        document.body,
    );
}

type LightboxTriggerProps = {
    images: LightboxImage[];
    startIndex?: number;
    className?: string;
    children: ReactNode;
};

/** Clickable wrapper that opens the lightbox. */
export function LightboxTrigger({
    images,
    startIndex = 0,
    className,
    children,
}: LightboxTriggerProps) {
    const [open, setOpen] = useState(false);
    const [index, setIndex] = useState(startIndex);

    if (images.length === 0) {
        return <>{children}</>;
    }

    return (
        <>
            <button
                type="button"
                className={cn('group relative block cursor-zoom-in text-left', className)}
                onClick={() => {
                    setIndex(startIndex);
                    setOpen(true);
                }}
                aria-label="View larger image"
            >
                {children}
                <span className="pointer-events-none absolute inset-0 rounded-[inherit] bg-black/0 transition group-hover:bg-black/10" />
            </button>
            <ImageLightbox
                images={images}
                open={open}
                index={index}
                onClose={() => setOpen(false)}
                onIndexChange={setIndex}
            />
        </>
    );
}

/** Build gallery from product photos + delivery package photo. */
export function orderItemLightboxImages(item: {
    product_name?: string;
    package_image?: string | null;
    product?: { images?: { path: string }[] } | null;
}): LightboxImage[] {
    const images: LightboxImage[] = [];
    const seen = new Set<string>();

    const push = (src: string | null | undefined, label: string) => {
        if (!src || seen.has(src)) return;
        seen.add(src);
        images.push({ src, alt: label, label });
    };

    for (const image of item.product?.images ?? []) {
        push(image.path, item.product_name ?? 'Product');
    }

    push(item.package_image, 'Delivery package');

    return images;
}
