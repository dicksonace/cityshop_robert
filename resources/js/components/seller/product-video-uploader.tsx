import { Film, LoaderCircle, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { productVideoUrl } from '@/types/marketplace';

const MAX_DURATION_SECONDS = 60;
const MAX_SIZE_MB = 50;
const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
const ACCEPTED = 'video/mp4,video/webm,video/quicktime,video/3gpp,video/x-m4v,.mp4,.webm,.mov,.m4v,.3gp';
const VIDEO_EXT = /\.(mp4|webm|mov|m4v|3gp|3gpp)$/i;

interface ProductVideoUploaderProps {
    existingPath?: string | null;
    existingDuration?: number | null;
    onChange: (file: File | null, duration: number | null) => void;
    onRemoveExisting?: () => void;
    removeExisting?: boolean;
    error?: string;
    className?: string;
}

function isLikelyVideoFile(file: File): boolean {
    if (file.type.startsWith('video/')) {
        return true;
    }

    return VIDEO_EXT.test(file.name);
}

function readVideoDuration(file: File): Promise<number> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;

        const cleanup = () => URL.revokeObjectURL(url);

        const timer = window.setTimeout(() => {
            cleanup();
            reject(new Error('Could not read video length. Try exporting as MP4 under 1 minute.'));
        }, 15000);

        video.onloadedmetadata = () => {
            window.clearTimeout(timer);
            const duration = video.duration;
            cleanup();
            if (!Number.isFinite(duration) || duration <= 0) {
                reject(new Error('Could not read video length. Try another file.'));
                return;
            }
            resolve(duration);
        };

        video.onerror = () => {
            window.clearTimeout(timer);
            cleanup();
            reject(new Error('Could not read this video. Use MP4, WebM, or MOV under 1 minute.'));
        };

        video.src = url;
    });
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export default function ProductVideoUploader({
    existingPath,
    existingDuration,
    onChange,
    onRemoveExisting,
    removeExisting = false,
    error,
    className,
}: ProductVideoUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [duration, setDuration] = useState<number | null>(null);
    const [localError, setLocalError] = useState<string | null>(null);
    const [checking, setChecking] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const clearSelection = () => {
        setFile(null);
        setDuration(null);
        setLocalError(null);
        onChange(null, null);
        if (inputRef.current) inputRef.current.value = '';
    };

    const handleSelect = async (selected: File | null) => {
        setLocalError(null);

        if (!selected) {
            clearSelection();
            return;
        }

        if (!isLikelyVideoFile(selected)) {
            setLocalError('Please choose a video file (MP4, WebM, MOV, or 3GP).');
            clearSelection();
            return;
        }

        if (selected.size > MAX_SIZE_BYTES) {
            setLocalError(`Video must be ${MAX_SIZE_MB}MB or smaller. This file is ${(selected.size / (1024 * 1024)).toFixed(1)}MB.`);
            clearSelection();
            return;
        }

        setChecking(true);
        try {
            const seconds = await readVideoDuration(selected);
            if (seconds > MAX_DURATION_SECONDS + 0.5) {
                setLocalError(`Video must be 1 minute or less. This one is ${formatDuration(seconds)}.`);
                clearSelection();
                return;
            }

            setFile(selected);
            setDuration(seconds);
            onChange(selected, Math.max(1, Math.round(seconds)));
        } catch (err) {
            setLocalError(err instanceof Error ? err.message : 'Could not validate video.');
            clearSelection();
        } finally {
            setChecking(false);
        }
    };

    const showExisting = !!existingPath && !removeExisting && !file;
    const displayDuration = file ? duration : existingDuration;

    return (
        <div className={cn('rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-4', className)}>
            <div className="mb-3 flex items-start gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
                    <Film className="h-5 w-5" />
                </div>
                <div>
                    <p className="font-semibold text-gray-900">Product video (optional)</p>
                    <p className="mt-0.5 text-sm text-gray-500">
                        Show buyers a short clip of the item. Max <strong>1 minute</strong>, MP4/WebM/MOV/3GP, up to {MAX_SIZE_MB}MB.
                    </p>
                </div>
            </div>

            {checking && (
                <div className="mb-3 flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm text-gray-600">
                    <LoaderCircle className="h-4 w-4 animate-spin" />
                    Checking video length…
                </div>
            )}

            {(previewUrl || showExisting) && (
                <div className="mb-3 overflow-hidden rounded-xl bg-black">
                    <video
                        key={previewUrl ?? existingPath ?? 'video'}
                        src={previewUrl ?? productVideoUrl(existingPath ?? undefined)}
                        controls
                        playsInline
                        className="max-h-64 w-full"
                    />
                    <div className="flex items-center justify-between gap-2 bg-gray-900 px-3 py-2 text-xs text-white/80">
                        <span>
                            {file?.name ?? 'Current video'}
                            {displayDuration != null && ` · ${formatDuration(displayDuration)}`}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 text-red-300 hover:bg-white/10 hover:text-red-200"
                            onClick={() => {
                                if (file) {
                                    clearSelection();
                                    return;
                                }
                                onRemoveExisting?.();
                            }}
                        >
                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                            Remove
                        </Button>
                    </div>
                </div>
            )}

            {!file && !showExisting && (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    disabled={checking}
                    className="flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-orange-200 bg-white px-4 py-8 text-center transition hover:border-orange-400 hover:bg-orange-50/50"
                >
                    <Upload className="h-8 w-8 text-orange-500" />
                    <span className="text-sm font-medium text-gray-900">Select product video</span>
                    <span className="text-xs text-gray-500">Optional · max 1 minute</span>
                </button>
            )}

            {(file || showExisting) && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-1"
                    onClick={() => inputRef.current?.click()}
                    disabled={checking}
                >
                    Replace video
                </Button>
            )}

            <input
                ref={inputRef}
                type="file"
                accept={ACCEPTED}
                className="hidden"
                onChange={(e) => handleSelect(e.target.files?.[0] ?? null)}
            />

            <InputError message={localError ?? error} className="mt-2" />
        </div>
    );
}

export { MAX_DURATION_SECONDS };
