import { Link } from '@inertiajs/react';
import { HTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

interface CityShopBrandProps extends HTMLAttributes<HTMLDivElement> {
    showText?: boolean;
    asLink?: boolean;
    size?: 'sm' | 'md' | 'lg';
    inverted?: boolean;
}

const iconSize = {
    sm: 'h-8 w-8',
    md: 'h-9 w-9',
    lg: 'h-11 w-11',
};

const letterSize = {
    sm: 'text-sm',
    md: 'text-lg',
    lg: 'text-xl',
};

const textSize = {
    sm: 'text-lg',
    md: 'text-xl',
    lg: 'text-2xl',
};

export default function CityShopBrand({
    className,
    showText = false,
    asLink = true,
    size = 'md',
    inverted = false,
    ...props
}: CityShopBrandProps) {
    const content = (
        <>
            <div className={cn('flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-orange-500 shadow-sm', iconSize[size])}>
                <span className={cn('font-bold text-white', letterSize[size])}>C</span>
            </div>
            {showText && (
                <span className={cn('font-bold', textSize[size], inverted ? 'text-white' : 'text-gray-900')}>
                    City<span className={inverted ? 'text-orange-200' : 'text-orange-500'}>Shop</span>
                </span>
            )}
        </>
    );

    const wrapperClass = cn('flex items-center gap-2.5', className);

    if (asLink) {
        return (
            <Link href={route('home')} className={wrapperClass}>
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
