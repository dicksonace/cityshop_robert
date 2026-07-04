import { Check, GripVertical, ImagePlus, Trash2, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ProductImage, productImageUrl } from '@/types/marketplace';

export interface ImagePreview {
    id: string;
    file?: File;
    previewUrl: string;
    isExisting?: boolean;
    existingId?: number;
}

interface ImageUploaderProps {
    maxImages?: number;
    existingImages?: ProductImage[];
    onChange: (files: File[], removeIds: number[]) => void;
    onConfirmedChange?: (confirmed: boolean) => void;
    error?: string;
}

export default function ImageUploader({
    maxImages = 5,
    existingImages = [],
    onChange,
    onConfirmedChange,
    error,
}: ImageUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previews, setPreviews] = useState<ImagePreview[]>([]);
    const [confirmed, setConfirmed] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [removedIds, setRemovedIds] = useState<number[]>([]);

    useEffect(() => {
        const existing: ImagePreview[] = existingImages
            .filter((img) => !removedIds.includes(img.id))
            .map((img) => ({
                id: `existing-${img.id}`,
                previewUrl: productImageUrl(img.path),
                isExisting: true,
                existingId: img.id,
            }));
        setPreviews((prev) => {
            const newFiles = prev.filter((p) => !p.isExisting);
            return [...existing, ...newFiles];
        });
    }, [existingImages, removedIds]);

    const notifyChange = useCallback(
        (items: ImagePreview[], removed: number[]) => {
            const files = items.filter((p) => p.file).map((p) => p.file!);
            onChange(files, removed);
        },
        [onChange],
    );

    const addFiles = (files: FileList | File[]) => {
        const incoming = Array.from(files).filter((f) => f.type.startsWith('image/'));
        const remaining = maxImages - previews.length;
        const toAdd = incoming.slice(0, remaining);

        if (toAdd.length === 0) return;

        setConfirmed(false);
        onConfirmedChange?.(false);
        const newPreviews: ImagePreview[] = toAdd.map((file) => ({
            id: `new-${Date.now()}-${Math.random()}`,
            file,
            previewUrl: URL.createObjectURL(file),
        }));

        const updated = [...previews, ...newPreviews];
        setPreviews(updated);
        notifyChange(updated, removedIds);
    };

    const removeImage = (id: string) => {
        const item = previews.find((p) => p.id === id);
        let newRemoved = removedIds;

        if (item?.isExisting && item.existingId) {
            newRemoved = [...removedIds, item.existingId];
            setRemovedIds(newRemoved);
        } else if (item?.previewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(item.previewUrl);
        }

        setConfirmed(false);
        onConfirmedChange?.(false);
        const updated = previews.filter((p) => p.id !== id);
        setPreviews(updated);
        notifyChange(updated, newRemoved);
    };

    const moveImage = (from: number, to: number) => {
        if (to < 0 || to >= previews.length) return;
        setConfirmed(false);
        onConfirmedChange?.(false);
        const updated = [...previews];
        const [moved] = updated.splice(from, 1);
        updated.splice(to, 0, moved);
        setPreviews(updated);
        notifyChange(updated, removedIds);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        addFiles(e.dataTransfer.files);
    };

    const canAddMore = previews.length < maxImages;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-semibold text-gray-900">Product Images</p>
                    <p className="text-xs text-gray-500">
                        Upload up to {maxImages} images. First image is the cover photo.
                    </p>
                </div>
                <span className={cn('rounded-full px-2.5 py-1 text-xs font-medium', previews.length > 0 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-500')}>
                    {previews.length}/{maxImages}
                </span>
            </div>

            {/* Drop zone */}
            {canAddMore && (
                <div
                    onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={handleDrop}
                    onClick={() => inputRef.current?.click()}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 transition-colors',
                        dragOver ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/50',
                    )}
                >
                    <Upload className="h-8 w-8 text-gray-400" />
                    <p className="mt-2 text-sm font-medium text-gray-700">Drop images here or click to browse</p>
                    <p className="mt-1 text-xs text-gray-400">PNG, JPG up to 5MB each</p>
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/*"
                        multiple
                        className="hidden"
                        onChange={(e) => {
                            if (e.target.files) addFiles(e.target.files);
                            e.target.value = '';
                        }}
                    />
                </div>
            )}

            {/* Previews grid */}
            {previews.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                    {previews.map((preview, index) => (
                        <div
                            key={preview.id}
                            className={cn(
                                'group relative overflow-hidden rounded-xl border-2 bg-white',
                                index === 0 ? 'border-orange-400 ring-2 ring-orange-100' : 'border-gray-100',
                                confirmed && 'ring-1 ring-green-200',
                            )}
                        >
                            <img src={preview.previewUrl} alt="" className="aspect-square w-full object-contain p-2" />

                            {index === 0 && (
                                <span className="absolute top-1.5 left-1.5 rounded-md bg-orange-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                    COVER
                                </span>
                            )}

                            <div className="absolute inset-0 flex items-center justify-center gap-1 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                {index > 0 && (
                                    <button type="button" onClick={() => moveImage(index, index - 1)} className="rounded-lg bg-white/90 p-1.5" title="Move left">
                                        ←
                                    </button>
                                )}
                                {index < previews.length - 1 && (
                                    <button type="button" onClick={() => moveImage(index, index + 1)} className="rounded-lg bg-white/90 p-1.5" title="Move right">
                                        →
                                    </button>
                                )}
                                <button type="button" onClick={() => removeImage(preview.id)} className="rounded-lg bg-red-500 p-1.5 text-white" title="Remove">
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>

                            <div className="absolute bottom-1 right-1 rounded bg-black/40 p-0.5 text-white opacity-0 group-hover:opacity-100">
                                <GripVertical className="h-3 w-3" />
                            </div>
                        </div>
                    ))}

                    {canAddMore && (
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="flex aspect-square flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 text-gray-400 transition-colors hover:border-orange-300 hover:text-orange-500"
                        >
                            <ImagePlus className="h-6 w-6" />
                            <span className="mt-1 text-xs">Add more</span>
                        </button>
                    )}
                </div>
            )}

            {/* Confirm step */}
            {previews.length > 0 && (
                <div className={cn('rounded-xl border p-4', confirmed ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50')}>
                    {confirmed ? (
                        <div className="flex items-center gap-2 text-sm text-green-700">
                            <Check className="h-5 w-5" />
                            <span className="font-medium">{previews.length} image{previews.length > 1 ? 's' : ''} confirmed — ready to publish</span>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-amber-800">
                                Review your images above. The first image will appear as the cover and slide on the product page.
                            </p>
                            <Button
                                type="button"
                                onClick={() => {
                                    setConfirmed(true);
                                    onConfirmedChange?.(true);
                                }}
                                className="shrink-0 bg-green-600 hover:bg-green-700"
                            >
                                <Check className="mr-2 h-4 w-4" />
                                Confirm Images
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

export function useImageConfirmed() {
    return { requireConfirmed: true };
}
