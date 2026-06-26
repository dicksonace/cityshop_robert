import { Camera, ImagePlus, LoaderCircle, Sparkles, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';

interface ImageSearchUploadProps {
    processing?: boolean;
    visionEnabled?: boolean;
    compact?: boolean;
}

export default function ImageSearchUpload({ processing = false, visionEnabled = false, compact = false }: ImageSearchUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);
    const [uploading, setUploading] = useState(false);

    const busy = processing || uploading;

    const onFile = (file: File | null) => {
        if (!file || !file.type.startsWith('image/')) return;
        setPreview(URL.createObjectURL(file));
    };

    const submit = () => {
        const file = inputRef.current?.files?.[0];
        if (!file) return;

        setUploading(true);
        router.post(route('search.image.store'), { image: file }, {
            forceFormData: true,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <div className={compact ? '' : 'mx-auto w-full max-w-xl'}>
            <div
                className={`relative overflow-hidden rounded-2xl border-2 border-dashed transition-colors ${
                    dragOver ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-white hover:border-orange-200'
                } ${compact ? 'p-4' : 'p-8'}`}
                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    const file = e.dataTransfer.files?.[0];
                    if (file && inputRef.current) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        inputRef.current.files = dt.files;
                        onFile(file);
                    }
                }}
            >
                {preview ? (
                    <div className="flex flex-col items-center gap-4">
                        <img src={preview} alt="Preview" className="max-h-48 rounded-xl object-contain shadow-sm" />
                        <p className="text-sm text-gray-500">Ready to find similar products</p>
                    </div>
                ) : (
                    <div className="text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-100 text-orange-500">
                            <Camera className="h-8 w-8" />
                        </div>
                        <p className="mt-4 font-semibold text-gray-900">
                            {compact ? 'Search by photo' : 'Upload a product photo'}
                        </p>
                        <p className="mt-1 text-sm text-gray-500">
                            Take a picture or upload from gallery — we&apos;ll find similar items
                        </p>
                    </div>
                )}

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    className="hidden"
                    onChange={(e) => onFile(e.target.files?.[0] ?? null)}
                />

                <div className="mt-6 flex flex-wrap justify-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => inputRef.current?.click()}
                        disabled={busy}
                    >
                        <ImagePlus className="mr-2 h-4 w-4" />
                        Choose image
                    </Button>
                    <Button
                        type="button"
                        className="bg-orange-500 hover:bg-orange-600"
                        disabled={busy || !preview}
                        onClick={submit}
                    >
                        {busy ? (
                            <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Upload className="mr-2 h-4 w-4" />
                        )}
                        Find similar
                    </Button>
                </div>

                {visionEnabled && (
                    <p className="mt-4 flex items-center justify-center gap-1 text-xs text-emerald-600">
                        <Sparkles className="h-3.5 w-3.5" />
                        AI vision enabled for smarter matches
                    </p>
                )}
            </div>
        </div>
    );
}
