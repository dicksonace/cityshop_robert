import { cn } from '@/lib/utils';

interface LoginErrorBannerProps {
    flashError?: string;
    errors?: Record<string, string>;
    variant?: 'light' | 'dark';
}

export default function LoginErrorBanner({ flashError, errors = {}, variant = 'light' }: LoginErrorBannerProps) {
    const fieldMessage = errors.login || errors.password;
    const message = flashError || fieldMessage;

    if (!message && Object.keys(errors).length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'rounded-lg border px-4 py-3 text-sm font-medium',
                variant === 'dark'
                    ? 'border-red-500/40 bg-red-500/10 text-red-300'
                    : 'border-red-200 bg-red-50 text-red-700',
            )}
            role="alert"
        >
            {message ?? 'Please check your login details and try again.'}
        </div>
    );
}
