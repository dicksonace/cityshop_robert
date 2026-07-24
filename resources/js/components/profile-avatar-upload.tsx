import { router, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { ChangeEvent, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { productImageUrl } from '@/types/marketplace';
import { cn } from '@/lib/utils';

type Props = {
    size?: 'md' | 'lg';
    className?: string;
    showRemove?: boolean;
    roleLabel?: string;
};

export default function ProfileAvatarUpload({
    size = 'lg',
    className,
    showRemove = true,
    roleLabel,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const inputRef = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const avatar = auth.user?.avatar;
    const name = auth.user?.name ?? '?';
    const dim = size === 'lg' ? 'h-20 w-20 text-2xl' : 'h-14 w-14 text-xl';

    const onPick = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            setError('Please choose an image file.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            setError('Image must be 5MB or smaller.');
            return;
        }

        setError(null);
        setUploading(true);

        const form = new FormData();
        form.append('avatar', file);

        router.post(route('profile.avatar.update'), form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: (errors) => {
                setError(errors.avatar ?? 'Could not upload profile picture.');
            },
        });
    };

    const remove = () => {
        if (!avatar || uploading) return;
        setUploading(true);
        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <div className={cn('flex items-center gap-4', className)}>
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                disabled={uploading}
                className={cn(
                    'relative shrink-0 overflow-hidden rounded-full bg-orange-100 text-orange-600 ring-2 ring-white shadow-sm transition hover:opacity-90 disabled:opacity-60',
                    dim,
                )}
                aria-label="Upload profile picture"
            >
                {avatar ? (
                    <img src={productImageUrl(avatar)} alt="" className="h-full w-full object-cover" />
                ) : (
                    <span className="flex h-full w-full items-center justify-center font-bold">
                        {name.charAt(0).toUpperCase()}
                    </span>
                )}
                <span className="absolute inset-x-0 bottom-0 flex items-center justify-center bg-black/45 py-1 text-white">
                    <Camera className="h-3.5 w-3.5" />
                </span>
            </button>

            <div className="min-w-0">
                <p className="truncate text-lg font-semibold text-gray-900">{name}</p>
                {roleLabel && <p className="text-sm text-gray-500">{roleLabel}</p>}
                <p className="mt-1 text-xs text-gray-500">
                    {uploading ? 'Uploading…' : 'Tap photo to upload a profile picture'}
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={uploading}
                        onClick={() => inputRef.current?.click()}
                    >
                        {avatar ? 'Change photo' : 'Upload photo'}
                    </Button>
                    {showRemove && avatar && (
                        <Button type="button" size="sm" variant="ghost" disabled={uploading} onClick={remove}>
                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                            Remove
                        </Button>
                    )}
                </div>
                <InputError className="mt-1" message={error ?? undefined} />
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={onPick}
            />
        </div>
    );
}
