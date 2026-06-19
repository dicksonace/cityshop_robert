import { HTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

/** CityShop icon mark — replaces the default Laravel logo everywhere. */
export default function AppLogoIcon({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={cn(
                'flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-orange-500 shadow-sm',
                !className?.match(/\b(h-|w-|size-)/) && 'h-9 w-9',
                className,
            )}
        >
            <span className="text-lg font-bold text-white">C</span>
        </div>
    );
}
