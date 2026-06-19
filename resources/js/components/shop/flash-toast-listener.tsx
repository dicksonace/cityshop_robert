import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { useToast } from '@/contexts/toast-context';
import { SharedData } from '@/types';

export default function FlashToastListener() {
    const { flash } = usePage<SharedData>().props;
    const toast = useToast();
    const lastSuccess = useRef<string | undefined>();
    const lastError = useRef<string | undefined>();

    useEffect(() => {
        if (flash?.success && flash.success !== lastSuccess.current) {
            lastSuccess.current = flash.success;
            toast.success(flash.success);
        }
        if (flash?.error && flash.error !== lastError.current) {
            lastError.current = flash.error;
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error, toast]);

    return null;
}
