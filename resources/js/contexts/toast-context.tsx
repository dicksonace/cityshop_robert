import { createContext, ReactNode, useCallback, useContext, useMemo, useState } from 'react';

import { cn } from '@/lib/utils';
import { CheckCircle2, Info, X, XCircle } from 'lucide-react';

export type ToastType = 'success' | 'error' | 'info';

export interface ToastItem {
    id: number;
    type: ToastType;
    message: string;
}

interface ToastContextValue {
    toast: (message: string, type?: ToastType) => void;
    success: (message: string) => void;
    error: (message: string) => void;
    info: (message: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

let toastId = 0;

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const dismiss = useCallback((id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const toast = useCallback(
        (message: string, type: ToastType = 'info') => {
            const id = ++toastId;
            setToasts((prev) => [...prev.slice(-4), { id, type, message }]);
            window.setTimeout(() => dismiss(id), 3500);
        },
        [dismiss],
    );

    const value = useMemo(
        () => ({
            toast,
            success: (message: string) => toast(message, 'success'),
            error: (message: string) => toast(message, 'error'),
            info: (message: string) => toast(message, 'info'),
        }),
        [toast],
    );

    return (
        <ToastContext.Provider value={value}>
            {children}
            <div
                className="pointer-events-none fixed bottom-4 left-4 z-[110] flex w-[min(100vw-2rem,360px)] flex-col gap-2"
                aria-live="polite"
            >
                {toasts.map((t) => (
                    <div
                        key={t.id}
                        className={cn(
                            'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg animate-in slide-in-from-left-4 fade-in duration-200',
                            t.type === 'success' && 'border-green-200 bg-green-50 text-green-900',
                            t.type === 'error' && 'border-red-200 bg-red-50 text-red-900',
                            t.type === 'info' && 'border-blue-200 bg-blue-50 text-blue-900',
                        )}
                    >
                        {t.type === 'success' && <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-green-600" />}
                        {t.type === 'error' && <XCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />}
                        {t.type === 'info' && <Info className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />}
                        <p className="flex-1 text-sm font-medium">{t.message}</p>
                        <button
                            type="button"
                            onClick={() => dismiss(t.id)}
                            className="shrink-0 rounded p-0.5 opacity-60 hover:opacity-100"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastContextValue {
    const ctx = useContext(ToastContext);
    if (!ctx) {
        throw new Error('useToast must be used within ToastProvider');
    }
    return ctx;
}

export function useToastOptional(): ToastContextValue | null {
    return useContext(ToastContext);
}
