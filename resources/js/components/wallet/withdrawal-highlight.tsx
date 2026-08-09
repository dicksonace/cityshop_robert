import { ArrowDownToLine, Building2, Smartphone } from 'lucide-react';
import { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface WithdrawalHighlightProps {
    title?: string;
    subtitle?: string;
    children: ReactNode;
    className?: string;
}

export default function WithdrawalHighlight({
    title = 'Withdraw funds',
    subtitle = 'Send your balance to Mobile Money or a Ghana bank account.',
    children,
    className,
}: WithdrawalHighlightProps) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border-2 border-orange-200 bg-gradient-to-br from-orange-50 via-white to-amber-50 shadow-md ring-1 ring-orange-100',
                className,
            )}
        >
            <div className="border-b border-orange-100 bg-gradient-to-r from-orange-500 to-amber-500 px-5 py-4 text-white sm:px-6">
                <div className="flex items-start gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur">
                        <ArrowDownToLine className="h-6 w-6" />
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-orange-100">MoMo &amp; bank payout</p>
                        <h2 className="text-lg font-bold sm:text-xl">{title}</h2>
                        <p className="mt-1 text-sm text-orange-50">{subtitle}</p>
                    </div>
                    <div className="ml-auto hidden shrink-0 items-center gap-2 text-white/40 sm:flex">
                        <Smartphone className="h-7 w-7" />
                        <Building2 className="h-7 w-7" />
                    </div>
                </div>
            </div>
            <div className="p-5 sm:p-6">{children}</div>
        </section>
    );
}
