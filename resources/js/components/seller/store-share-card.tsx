import { Check, Copy, ExternalLink, Share2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { storePageUrl } from '@/components/shop/seller-store-link';

interface StoreShareCardProps {
    slug: string;
    storeName: string;
    storeUrl: string;
}

export default function StoreShareCard({ slug, storeName, storeUrl }: StoreShareCardProps) {
    const [copied, setCopied] = useState(false);

    const copyLink = async () => {
        await navigator.clipboard.writeText(storeUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const shareWhatsApp = () => {
        const text = encodeURIComponent(`Shop at ${storeName} on CityShop! ${storeUrl}`);
        window.open(`https://wa.me/?text=${text}`, '_blank');
    };

    const shareFacebook = () => {
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(storeUrl)}`, '_blank');
    };

    return (
        <div className="rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-50 to-blue-50 p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h3 className="font-bold text-gray-900">Your Store Link</h3>
                    <p className="mt-1 text-sm text-gray-500">Share this link so customers can visit your store directly.</p>
                </div>
                <a
                    href={storePageUrl(slug)}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-sm font-medium text-orange-500 hover:underline"
                >
                    View Store <ExternalLink className="h-3.5 w-3.5" />
                </a>
            </div>

            <div className="mt-4 flex gap-2">
                <Input value={storeUrl} readOnly className="bg-white text-sm" />
                <Button onClick={copyLink} variant="outline" className="shrink-0">
                    {copied ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <Button onClick={copyLink} size="sm" className="bg-orange-500 hover:bg-orange-600">
                    <Copy className="mr-2 h-4 w-4" />
                    {copied ? 'Copied!' : 'Copy Link'}
                </Button>
                <Button onClick={shareWhatsApp} size="sm" variant="outline" className="border-green-200 text-green-700 hover:bg-green-50">
                    <Share2 className="mr-2 h-4 w-4" />
                    WhatsApp
                </Button>
                <Button onClick={shareFacebook} size="sm" variant="outline">
                    <Share2 className="mr-2 h-4 w-4" />
                    Facebook
                </Button>
            </div>

            <p className="mt-3 text-xs text-gray-400">
                Example: <span className="font-mono text-gray-600">cityshop.com/store/{slug}</span>
            </p>
        </div>
    );
}
