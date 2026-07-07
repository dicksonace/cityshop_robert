import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type PasswordInputProps = Omit<React.ComponentProps<'input'>, 'type'> & {
    wrapperClassName?: string;
    toggleClassName?: string;
};

const PasswordInput = React.forwardRef<HTMLInputElement, PasswordInputProps>(
    ({ className, wrapperClassName, toggleClassName, ...props }, ref) => {
        const [visible, setVisible] = React.useState(false);

        return (
            <div className={cn('relative', wrapperClassName)}>
                <Input
                    ref={ref}
                    type={visible ? 'text' : 'password'}
                    className={cn('pr-10', className)}
                    {...props}
                />
                <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setVisible((v) => !v)}
                    aria-label={visible ? 'Hide password' : 'Show password'}
                    aria-pressed={visible}
                    className={cn(
                        'absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition-colors hover:text-gray-600 focus:outline-none',
                        toggleClassName,
                    )}
                >
                    {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
            </div>
        );
    },
);

PasswordInput.displayName = 'PasswordInput';

export { PasswordInput };
