import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { cn } from '@/lib/utils';
import { sellerOrderStages, sellerOrdersStageHref } from '@/lib/seller-order-stages';

interface OrderPipelineCardsProps {
    counts: Record<string, number>;
    activeSlug?: string;
    compact?: boolean;
}

export default function OrderPipelineCards({ counts, activeSlug, compact }: OrderPipelineCardsProps) {
    return (
        <div className={cn('grid gap-3', compact ? 'grid-cols-1 sm:grid-cols-2' : 'sm:grid-cols-2 xl:grid-cols-3')}>
            {sellerOrderStages.map((stage) => {
                const count = counts[stage.countKey] ?? 0;
                const active = activeSlug === stage.slug;
                const Icon = stage.icon;
                const href = sellerOrdersStageHref(stage.slug);

                return (
                    <Link
                        key={stage.slug}
                        href={href}
                        className={cn(
                            'group relative block overflow-hidden rounded-[1.35rem] border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            stage.cardBg,
                            active && `ring-2 ${stage.ring}`,
                        )}
                    >
                        <div className="flex items-start gap-3">
                            <div className={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl', stage.iconBg)}>
                                <Icon className="h-5 w-5" strokeWidth={1.75} />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-slate-500">{stage.label}</p>
                                <p className={cn('mt-1 text-3xl font-bold tabular-nums tracking-tight', count > 0 ? 'text-slate-900' : 'text-slate-300')}>
                                    {count}
                                </p>
                                {!compact && (
                                    <p className="mt-1 line-clamp-2 text-xs text-slate-400">{stage.description}</p>
                                )}
                            </div>
                            {count > 0 && (
                                <span className="flex h-2.5 w-2.5 shrink-0">
                                    <span className="absolute inline-flex h-2.5 w-2.5 animate-ping rounded-full bg-orange-400 opacity-60" />
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-orange-500" />
                                </span>
                            )}
                        </div>

                        <div className={cn('mt-4 flex items-center justify-end gap-1 text-sm font-semibold', stage.accent)}>
                            <span>{stage.cta}</span>
                            <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}
