import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

import { productImageUrl } from '@/types/marketplace';
import { StoreHeroSettings, StoreThemeSettings } from '@/types/store-customization';

interface StoreHeroProps {
    hero: StoreHeroSettings;
    theme: StoreThemeSettings;
    storeName: string;
    slogan?: string;
    description?: string;
    logoUrl?: string | null;
    coverUrl?: string | null;
    shopPhotoUrl?: string | null;
}

export default function StoreHero({
    hero,
    theme,
    storeName,
    slogan,
    description,
    logoUrl,
    coverUrl,
    shopPhotoUrl,
}: StoreHeroProps) {
    const [slide, setSlide] = useState(0);
    const images = hero.images.length > 0 ? hero.images : coverUrl ? [coverUrl] : [];
    const logo = logoUrl ?? shopPhotoUrl;

    useEffect(() => {
        if (hero.type !== 'slideshow' || images.length <= 1) return;

        const timer = setInterval(() => {
            setSlide((prev) => (prev + 1) % images.length);
        }, Math.max(hero.autoplay_seconds, 2) * 1000);

        return () => clearInterval(timer);
    }, [hero.type, hero.autoplay_seconds, images.length]);

    const gradientStyle = {
        background: `linear-gradient(135deg, ${theme.primary_color}, ${theme.secondary_color})`,
    };

    if (hero.type === 'minimal') {
        return (
            <div className="border-b border-gray-100 bg-white px-4 py-8 sm:px-6">
                <div className="mx-auto flex max-w-7xl items-center gap-4">
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl text-2xl font-bold text-white" style={gradientStyle}>
                        {logo ? <img src={productImageUrl(logo)} alt="" className="h-full w-full object-cover" /> : storeName.charAt(0)}
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold" style={{ color: theme.text_color }}>{storeName}</h1>
                        {slogan && <p className="text-sm text-gray-500">{slogan}</p>}
                        {description && <p className="mt-1 text-sm text-gray-600 line-clamp-2">{description}</p>}
                    </div>
                </div>
            </div>
        );
    }

    if (hero.type === 'slideshow' && images.length > 0) {
        return (
            <div className="relative overflow-hidden" style={{ minHeight: '220px' }}>
                {images.map((img, i) => (
                    <div
                        key={img}
                        className={`absolute inset-0 transition-opacity duration-700 ${i === slide ? 'opacity-100' : 'opacity-0'}`}
                    >
                        <img src={productImageUrl(img)} alt="" className="h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-black/40" />
                    </div>
                ))}
                <div className="relative z-10 mx-auto max-w-7xl px-4 py-10 text-white sm:px-6 sm:py-14">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/20 text-2xl font-bold backdrop-blur-sm">
                            {logo ? <img src={productImageUrl(logo)} alt="" className="h-full w-full object-cover" /> : storeName.charAt(0)}
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold sm:text-3xl">{storeName}</h1>
                            {slogan && <p className="mt-1 text-white/85">{slogan}</p>}
                        </div>
                    </div>
                </div>
                {hero.show_arrows && images.length > 1 && (
                    <>
                        <button type="button" onClick={() => setSlide((s) => (s - 1 + images.length) % images.length)} className="absolute top-1/2 left-2 z-20 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white">
                            <ChevronLeft className="h-5 w-5" />
                        </button>
                        <button type="button" onClick={() => setSlide((s) => (s + 1) % images.length)} className="absolute top-1/2 right-2 z-20 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white">
                            <ChevronRight className="h-5 w-5" />
                        </button>
                    </>
                )}
                {hero.show_indicators && images.length > 1 && (
                    <div className="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 gap-2">
                        {images.map((_, i) => (
                            <button key={i} type="button" onClick={() => setSlide(i)} className={`h-2 w-2 rounded-full ${i === slide ? 'bg-white' : 'bg-white/50'}`} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    const bgImage = images[0] ?? coverUrl;

    return (
        <div className="relative overflow-hidden" style={bgImage ? undefined : gradientStyle}>
            {bgImage && (
                <>
                    <img src={productImageUrl(bgImage)} alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-black/50" />
                </>
            )}
            <div className="relative z-10 mx-auto max-w-7xl px-4 py-8 text-white sm:px-6 sm:py-12">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/15 text-2xl font-bold ring-2 ring-white/20 backdrop-blur-sm sm:h-20 sm:w-20">
                            {logo ? <img src={productImageUrl(logo)} alt="" className="h-full w-full object-cover" /> : storeName.charAt(0)}
                        </div>
                        <div>
                            <h1 className="text-xl font-bold sm:text-3xl">{storeName}</h1>
                            {slogan && <p className="mt-1 text-sm text-white/80 sm:text-base">{slogan}</p>}
                        </div>
                    </div>
                </div>
                {description && <p className="mt-4 max-w-2xl text-sm text-white/80">{description}</p>}
            </div>
        </div>
    );
}
