import { Link } from '@inertiajs/react';
import { ArrowRight, Landmark } from 'lucide-react';

import { cn } from '@/lib/utils';

interface ManualTopUpPromptProps {
    href: string;
    className?: string;
    title?: string;
    description?: string;
    cta?: string;
}

export default function ManualTopUpPrompt({
    href,
    className,
    title = 'Paying a large amount?',
    description = 'Send money to CityShop MoMo or bank, then submit proof — admin credits your wallet.',
    cta = 'Use manual payment',
}: ManualTopUpPromptProps) {
    return (
        <Link
            href={href}
            className={cn(
                'group relative mt-1 flex items-start gap-3 overflow-hidden rounded-xl border border-sky-200/80 bg-gradient-to-br from-sky-50 via-white to-cyan-50/80 p-3.5 shadow-sm ring-1 ring-sky-100 transition',
                'hover:border-sky-300 hover:shadow-md hover:ring-sky-200',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2',
                className,
            )}
        >
            <div
                className="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-sky-200/40 blur-2xl"
                aria-hidden
            />
            <div className="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-600 to-cyan-600 text-white shadow-sm">
                <Landmark className="h-5 w-5" aria-hidden />
            </div>
            <div className="relative min-w-0 flex-1">
                <p className="text-[11px] font-semibold uppercase tracking-wider text-sky-600">Manual top-up</p>
                <p className="mt-0.5 font-semibold text-gray-900">{title}</p>
                <p className="mt-1 text-sm leading-snug text-gray-600">{description}</p>
                <span className="mt-2.5 inline-flex items-center gap-1 text-sm font-semibold text-sky-700">
                    {cta}
                    <ArrowRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden />
                </span>
            </div>
        </Link>
    );
}
