import { Link } from '@inertiajs/react';
import { HTMLAttributes } from 'react';

import { APP_LOGO_ALT, APP_LOGO_SRC } from '@/lib/brand';
import { cn } from '@/lib/utils';

interface CityShopBrandProps extends HTMLAttributes<HTMLDivElement> {
    showText?: boolean;
    asLink?: boolean;
    href?: string;
    size?: 'sm' | 'md' | 'lg';
    inverted?: boolean;
}

const logoHeight = {
    sm: 'h-8 max-w-[7rem]',
    md: 'h-10 max-w-[9rem]',
    lg: 'h-14 max-w-[11rem]',
};

export default function CityShopBrand({
    className,
    showText = false,
    asLink = true,
    href,
    size = 'md',
    inverted: _inverted = false,
    ...props
}: CityShopBrandProps) {
    const heightClass = showText ? logoHeight.lg : logoHeight[size];

    const content = (
        <img
            src={APP_LOGO_SRC}
            alt={APP_LOGO_ALT}
            className={cn('w-auto shrink-0 object-contain object-left', heightClass)}
        />
    );

    const wrapperClass = cn('flex items-center', className);

    if (asLink) {
        return (
            <Link href={href ?? route('home')} className={wrapperClass}>
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
