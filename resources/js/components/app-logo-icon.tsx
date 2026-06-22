import { HTMLAttributes } from 'react';

import { APP_LOGO_ALT, APP_LOGO_SRC } from '@/lib/brand';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({ className, ...props }: HTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src={APP_LOGO_SRC}
            alt={APP_LOGO_ALT}
            className={cn('h-9 w-auto shrink-0 object-contain object-left', className)}
        />
    );
}
