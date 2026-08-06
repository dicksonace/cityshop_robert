import { Maximize2, Play, Volume2, VolumeX, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';

type ChatVideoBubbleProps = {
    src: string;
    caption?: string | null;
    className?: string;
};

/**
 * Chat videos open in a full-screen player (same idea as the mobile app).
 * Inline &lt;video controls&gt; inside the floating sheet often swallows taps on
 * phones, so sellers/buyers see a play button that does nothing.
 */
export default function ChatVideoBubble({ src, caption, className }: ChatVideoBubbleProps) {
    const [open, setOpen] = useState(false);

    if (!src) {
        return null;
    }

    return (
        <div className={className}>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group relative block w-full overflow-hidden rounded-xl bg-black text-left"
                aria-label="Play video"
            >
                <video
                    src={src}
                    muted
                    playsInline
                    preload="metadata"
                    className="max-h-64 w-full bg-black object-cover"
                    // First frame as poster without autoplay fighting the overlay.
                    onLoadedMetadata={(e) => {
                        const el = e.currentTarget;
                        try {
                            el.currentTime = 0.1;
                        } catch {
                            // Some browsers reject seeks before data is ready.
                        }
                    }}
                />
                <span className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/35 transition group-hover:bg-black/45">
                    <span className="flex h-14 w-14 items-center justify-center rounded-full bg-black/60 text-white shadow-lg ring-2 ring-white/40">
                        <Play className="h-7 w-7 fill-current pl-0.5" />
                    </span>
                    <span className="text-xs font-semibold text-white drop-shadow">Tap to play</span>
                </span>
                <span className="pointer-events-none absolute bottom-2 left-2 flex items-center gap-1.5 text-white/90">
                    <Maximize2 className="h-3.5 w-3.5" />
                </span>
            </button>
            {caption?.trim() ? <p className="px-2 py-1.5 text-sm">{caption}</p> : null}
            <ChatVideoViewer src={src} open={open} onClose={() => setOpen(false)} />
        </div>
    );
}

type ChatVideoViewerProps = {
    src: string;
    open: boolean;
    onClose: () => void;
};

function ChatVideoViewer({ src, open, onClose }: ChatVideoViewerProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [muted, setMuted] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!open) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);

        const video = videoRef.current;
        if (video) {
            setFailed(false);
            video.muted = false;
            setMuted(false);
            const play = video.play();
            if (play && typeof play.catch === 'function') {
                play.catch(() => {
                    // Autoplay with sound can be blocked — retry muted so the clip still starts.
                    video.muted = true;
                    setMuted(true);
                    void video.play().catch(() => setFailed(true));
                });
            }
        }

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onKey);
            video?.pause();
        };
    }, [open, onClose, src]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            className="fixed inset-0 z-[120] flex flex-col bg-black/95 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
            aria-label="Video player"
            onClick={onClose}
        >
            <div className="flex items-center justify-between gap-3 px-4 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <button
                    type="button"
                    onClick={onClose}
                    className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="Close video"
                >
                    <X className="h-5 w-5" />
                </button>
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        const video = videoRef.current;
                        if (!video) return;
                        video.muted = !video.muted;
                        setMuted(video.muted);
                    }}
                    className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label={muted ? 'Unmute' : 'Mute'}
                >
                    {muted ? <VolumeX className="h-5 w-5" /> : <Volume2 className="h-5 w-5" />}
                </button>
            </div>

            <div
                className="relative flex min-h-0 flex-1 items-center justify-center px-3 pb-[max(1rem,env(safe-area-inset-bottom))]"
                onClick={(e) => e.stopPropagation()}
            >
                {failed ? (
                    <p className="text-sm text-white/80">Could not play this video.</p>
                ) : (
                    <video
                        ref={videoRef}
                        src={src}
                        controls
                        playsInline
                        autoPlay
                        className={cn(
                            'max-h-full max-w-full rounded-lg bg-black shadow-2xl',
                            'animate-in zoom-in-95 fade-in duration-200',
                        )}
                        onError={() => setFailed(true)}
                    />
                )}
            </div>
        </div>,
        document.body,
    );
}
