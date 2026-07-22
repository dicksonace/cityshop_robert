import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { useToast } from '@/contexts/toast-context';
import { SharedData } from '@/types';

/**
 * Flash → toast for panels that do not already show a layout flash banner.
 * Shop pages skip this because ShopLayout renders flash at the top.
 */
export default function FlashToastListener() {
    const page = usePage<SharedData>();
    const flash = page.props.flash;
    const toast = useToast();
    const lastSuccess = useRef<string | undefined>();
    const lastError = useRef<string | undefined>();

    const pageName = typeof page.component === 'string' ? page.component : '';
    const hasValidationErrors = Object.keys(page.props.errors ?? {}).length > 0;
    const shopHandled =
        pageName.startsWith('shop/') ||
        pageName === 'home' ||
        pageName === 'welcome' ||
        pageName.startsWith('store');
    const authHandled = pageName.startsWith('auth/');

    useEffect(() => {
        if (shopHandled || authHandled) {
            return;
        }

        if (flash?.success && flash.success !== lastSuccess.current) {
            lastSuccess.current = flash.success;
            toast.success(flash.success);
        }
        if (flash?.error && !hasValidationErrors && flash.error !== lastError.current) {
            lastError.current = flash.error;
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error, toast, shopHandled, authHandled, hasValidationErrors]);

    return null;
}
