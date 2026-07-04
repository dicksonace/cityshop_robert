import { Trash2, UploadCloud } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface MultiImageUploadFieldProps {
    id?: string;
    label: string;
    hint?: string;
    required?: boolean;
    maxFiles?: number;
    maxSizeMb?: number;
    value: File[];
    onChange: (files: File[]) => void;
    existingUrls?: string[];
    onRemoveExisting?: (url: string) => void;
    error?: string;
    className?: string;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function MultiImageUploadField({
    id,
    label,
    hint,
    required = false,
    maxFiles = 8,
    maxSizeMb = 5,
    value,
    onChange,
    existingUrls = [],
    onRemoveExisting,
    error,
    className,
}: MultiImageUploadFieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragOver, setDragOver] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const previews = useMemo(
        () => value.map((file) => ({ file, url: URL.createObjectURL(file) })),
        [value],
    );

    useEffect(() => {
        return () => {
            previews.forEach((preview) => URL.revokeObjectURL(preview.url));
        };
    }, [previews]);

    const remaining = maxFiles - existingUrls.length - value.length;

    const addFiles = (files: FileList | null) => {
        setLocalError(null);
        if (!files?.length) return;

        const maxBytes = maxSizeMb * 1024 * 1024;
        const incoming = Array.from(files).filter((file) => file.type.startsWith('image/'));

        if (incoming.length === 0) {
            setLocalError('Please select image files (JPG, PNG, GIF, WEBP).');
            return;
        }

        const tooLarge = incoming.find((file) => file.size > maxBytes);
        if (tooLarge) {
            setLocalError(`"${tooLarge.name}" is too large. Maximum size is ${maxSizeMb}MB.`);
            return;
        }

        if (remaining <= 0) {
            setLocalError(`You can upload up to ${maxFiles} images.`);
            return;
        }

        onChange([...value, ...incoming.slice(0, remaining)]);
        if (inputRef.current) inputRef.current.value = '';
    };

    const removeFile = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    const displayError = localError ?? error;
    const hasAny = existingUrls.length > 0 || value.length > 0;

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id} className="text-sm font-medium text-gray-900">
                {label}
                {required && <span className="ml-0.5 text-red-500">*</span>}
            </Label>

            <div
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (remaining > 0) inputRef.current?.click();
                    }
                }}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    addFiles(e.dataTransfer.files);
                }}
                onClick={() => remaining > 0 && inputRef.current?.click()}
                className={cn(
                    'cursor-pointer rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors sm:px-6',
                    dragOver ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40',
                    displayError && 'border-red-300 bg-red-50/40',
                    remaining <= 0 && 'cursor-default opacity-80',
                )}
            >
                <UploadCloud className="mx-auto h-10 w-10 text-gray-400" />
                <p className="mt-3 text-sm font-semibold text-gray-800">Click to upload or drag and drop</p>
                {hint && <p className="mx-auto mt-1 max-w-md text-xs text-gray-500">{hint}</p>}
                <p className="mt-3 text-xs text-gray-400">
                    JPG, PNG, GIF, WEBP accepted • Maximum file size: {maxSizeMb}MB • Up to {maxFiles} images
                </p>
                <input
                    id={id}
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple
                    className="hidden"
                    onChange={(e) => addFiles(e.target.files)}
                />
            </div>

            {hasAny && (
                <div className="flex flex-wrap gap-2">
                    {existingUrls.map((url) => (
                        <div key={url} className="group relative overflow-hidden rounded-lg border-2 border-emerald-500 bg-white p-0.5 shadow-sm">
                            <img src={url} alt="" className="h-16 w-16 object-cover" />
                            {onRemoveExisting && (
                                <button
                                    type="button"
                                    onClick={() => onRemoveExisting(url)}
                                    className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100"
                                >
                                    <Trash2 className="h-4 w-4 text-white" />
                                </button>
                            )}
                        </div>
                    ))}
                    {previews.map((preview, index) => (
                        <div key={`${preview.file.name}-${index}`} className="group relative overflow-hidden rounded-lg border-2 border-emerald-500 bg-white p-0.5 shadow-sm">
                            <img src={preview.url} alt="" className="h-16 w-16 object-cover" />
                            <button
                                type="button"
                                onClick={() => removeFile(index)}
                                className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100"
                            >
                                <Trash2 className="h-4 w-4 text-white" />
                            </button>
                            <span className="sr-only">{preview.file.name} ({formatFileSize(preview.file.size)})</span>
                        </div>
                    ))}
                </div>
            )}

            <InputError message={displayError} />
        </div>
    );
}
