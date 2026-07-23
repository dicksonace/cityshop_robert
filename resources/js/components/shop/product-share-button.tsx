import { Check, Share2 } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

interface ProductShareButtonProps {
    productName: string;
    className?: string;
    size?: 'sm' | 'md';
}

export default function ProductShareButton({ productName, className, size = 'md' }: ProductShareButtonProps) {
    const [copied, setCopied] = useState(false);

    const sizeClass = size === 'md' ? 'h-10 w-10' : 'h-8 w-8';
    const iconSize = size === 'md' ? 'h-5 w-5' : 'h-4 w-4';

    const share = async () => {
        const url = window.location.href;
        const title = productName;
        const text = `Check out ${productName} on CityShop`;

        try {
            if (typeof navigator.share === 'function') {
                await navigator.share({ title, text, url });
                return;
            }
        } catch (error) {
            // User cancelled or share failed — fall through to copy.
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }
        }

        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy this link:', url);
        }
    };

    return (
        <button
            type="button"
            onClick={share}
            aria-label={copied ? 'Link copied' : 'Share product'}
            title={copied ? 'Link copied' : 'Share'}
            className={cn(
                'flex items-center justify-center rounded-lg border transition-colors',
                sizeClass,
                copied
                    ? 'border-green-200 bg-green-50 text-green-600'
                    : 'border-gray-100 text-gray-400 hover:border-orange-200 hover:text-orange-500',
                className,
            )}
        >
            {copied ? <Check className={iconSize} /> : <Share2 className={iconSize} />}
        </button>
    );
}
