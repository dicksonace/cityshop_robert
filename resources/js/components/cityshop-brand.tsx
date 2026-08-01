import { Link } from '@inertiajs/react';
import { HTMLAttributes } from 'react';

import { APP_LOGO_ALT, APP_LOGO_SRC, APP_LOGO_TILE_SRC } from '@/lib/brand';
import { cn } from '@/lib/utils';

interface CityShopBrandProps extends HTMLAttributes<HTMLDivElement> {
    showText?: boolean;
    asLink?: boolean;
    href?: string;
    size?: 'sm' | 'md' | 'lg';
    inverted?: boolean;
}

const sizes = {
    sm: { mark: 'h-9 w-9', text: 'text-lg' },
    md: { mark: 'h-10 w-10', text: 'text-xl' },
    lg: { mark: 'h-14 w-14', text: 'text-2xl' },
};

export default function CityShopBrand({
    className,
    showText = false,
    asLink = true,
    href,
    size = 'md',
    inverted = false,
    ...props
}: CityShopBrandProps) {
    const scale = sizes[showText ? (size === 'sm' ? 'md' : size) : size];

    const content = (
        <>
            <img src={inverted ? APP_LOGO_TILE_SRC : APP_LOGO_SRC} alt="" aria-hidden className={cn('shrink-0 object-contain', scale.mark)} />
            <span
                className={cn(
                    'leading-none font-extrabold tracking-tight',
                    scale.text,
                    inverted ? 'text-white' : 'text-gray-900',
                    // Headers keep the mark only on the narrowest screens.
                    !showText && 'hidden sm:inline',
                )}
            >
                City<span className={inverted ? 'text-orange-200' : 'text-orange-600'}>Shop</span>
            </span>
        </>
    );

    const wrapperClass = cn('flex items-center gap-2', className);

    if (asLink) {
        return (
            <Link href={href ?? route('home')} className={wrapperClass} aria-label={APP_LOGO_ALT}>
                {content}
            </Link>
        );
    }

    return (
        <div className={wrapperClass} {...props}>
            {content}
        </div>
    );
}
