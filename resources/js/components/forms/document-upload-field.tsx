import { FileText, Trash2, UploadCloud } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface DocumentUploadFieldProps {
    id?: string;
    label: string;
    hint?: string;
    required?: boolean;
    accept?: string;
    maxSizeMb?: number;
    value: File | null;
    onChange: (file: File | null) => void;
    /** Already-saved image URL (shown when no new file is selected). */
    existingUrl?: string | null;
    onClearExisting?: () => void;
    error?: string;
    className?: string;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isImageFile(file: File): boolean {
    return file.type.startsWith('image/');
}

function acceptedLabel(accept: string): string {
    const parts = accept
        .split(',')
        .map((part) => part.trim().replace('image/', '').replace('.', '').toUpperCase())
        .filter(Boolean);
    return [...new Set(parts)].join(', ') || 'JPG, PNG, PDF';
}

export default function DocumentUploadField({
    id,
    label,
    hint,
    required = true,
    accept = 'image/jpeg,image/png,image/webp,image/gif,.pdf',
    maxSizeMb = 5,
    value,
    onChange,
    existingUrl = null,
    onClearExisting,
    error,
    className,
}: DocumentUploadFieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragOver, setDragOver] = useState(false);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [localError, setLocalError] = useState<string | null>(null);
    const [clearedExisting, setClearedExisting] = useState(false);

    useEffect(() => {
        if (!value) {
            setPreviewUrl(null);
            return;
        }

        if (isImageFile(value)) {
            const url = URL.createObjectURL(value);
            setPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }

        setPreviewUrl(null);
    }, [value]);

    const shownExistingUrl = !value && !clearedExisting ? existingUrl : null;
    const hasSelection = Boolean(value || shownExistingUrl);

    const validateAndSet = (file: File | null) => {
        setLocalError(null);
        if (!file) {
            onChange(null);
            return;
        }

        const maxBytes = maxSizeMb * 1024 * 1024;
        if (file.size > maxBytes) {
            setLocalError(`File is too large. Maximum size is ${maxSizeMb}MB.`);
            return;
        }

        const accepted = accept.split(',').map((item) => item.trim().toLowerCase());
        const fileType = file.type.toLowerCase();
        const fileExt = `.${file.name.split('.').pop()?.toLowerCase() ?? ''}`;
        const allowed = accepted.some(
            (item) => item === fileType || item === fileExt || (item.endsWith('/*') && fileType.startsWith(item.replace('/*', '/'))),
        );

        if (!allowed) {
            setLocalError(`Invalid file type. Accepted: ${acceptedLabel(accept)}.`);
            return;
        }

        setClearedExisting(false);
        onChange(file);
    };

    const handleFiles = (files: FileList | null) => {
        const file = files?.[0] ?? null;
        validateAndSet(file);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const clearFile = () => {
        setLocalError(null);
        onChange(null);
        if (shownExistingUrl) {
            setClearedExisting(true);
            onClearExisting?.();
        }
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const displayError = localError ?? error;
    const displayPreview = previewUrl ?? shownExistingUrl;

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
                        inputRef.current?.click();
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
                    handleFiles(e.dataTransfer.files);
                }}
                onClick={() => inputRef.current?.click()}
                className={cn(
                    'cursor-pointer rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors sm:px-6',
                    dragOver ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40',
                    displayError && 'border-red-300 bg-red-50/40',
                )}
            >
                {hasSelection ? (
                    <div className="flex flex-col items-center gap-3" onClick={(e) => e.stopPropagation()}>
                        {displayPreview ? (
                            <img src={displayPreview} alt={`${label} preview`} className="max-h-44 w-full max-w-sm rounded-lg object-contain shadow-sm" />
                        ) : (
                            <div className="flex h-28 w-28 flex-col items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                                <FileText className="h-10 w-10 text-orange-500" />
                                <span className="mt-2 text-xs font-medium uppercase text-gray-500">PDF</span>
                            </div>
                        )}
                        <div className="max-w-sm text-center">
                            <p className="truncate text-sm font-semibold text-gray-900">
                                {value?.name ?? 'Current image'}
                            </p>
                            {value && <p className="text-xs text-gray-500">{formatFileSize(value.size)}</p>}
                        </div>
                        <div className="flex flex-wrap justify-center gap-2">
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    inputRef.current?.click();
                                }}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Replace
                            </button>
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    clearFile();
                                }}
                                className="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                Remove
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <UploadCloud className="mx-auto h-10 w-10 text-gray-400" />
                        <p className="mt-3 text-sm font-semibold text-gray-800">Click to upload or drag and drop</p>
                        {hint && <p className="mx-auto mt-1 max-w-md text-xs text-gray-500">{hint}</p>}
                        <p className="mt-3 text-xs text-gray-400">
                            {acceptedLabel(accept)} accepted • Maximum file size: {maxSizeMb}MB
                        </p>
                    </>
                )}

                <input
                    id={id}
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    className="hidden"
                    onChange={(e) => handleFiles(e.target.files)}
                />
            </div>

            {hasSelection && (
                <div className="flex items-center gap-2">
                    <div className="overflow-hidden rounded-lg border-2 border-emerald-500 bg-white p-0.5 shadow-sm">
                        {displayPreview ? (
                            <img src={displayPreview} alt="" className="h-14 w-14 object-cover" />
                        ) : (
                            <div className="flex h-14 w-14 items-center justify-center bg-gray-50">
                                <FileText className="h-6 w-6 text-orange-500" />
                            </div>
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-xs font-medium text-gray-800">{value?.name ?? 'Current image'}</p>
                        <p className="text-[11px] text-emerald-600">Selected — preview ready</p>
                    </div>
                </div>
            )}

            <InputError message={displayError} />
        </div>
    );
}
