import { Download, FileText } from 'lucide-react';

import { cn } from '@/lib/utils';

function formatSize(bytes?: number | null): string {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

interface ChatFileBubbleProps {
    url: string;
    name?: string | null;
    size?: number | null;
    className?: string;
}

export default function ChatFileBubble({ url, name, size, className }: ChatFileBubbleProps) {
    const label = (name || 'File').trim() || 'File';
    const sizeLabel = formatSize(size);

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className={cn(
                'flex min-w-[14rem] items-center gap-2.5 p-3 text-left transition hover:bg-blue-50/70',
                className,
            )}
        >
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                <FileText className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="line-clamp-2 text-sm font-bold text-gray-900">{label}</p>
                <p className="mt-0.5 text-[11px] text-gray-400">
                    {sizeLabel ? `${sizeLabel} · Tap to open` : 'Tap to open'}
                </p>
            </div>
            <Download className="h-4 w-4 shrink-0 text-orange-500" />
        </a>
    );
}
