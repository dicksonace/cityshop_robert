import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { cn } from '@/lib/utils';
import { sellerOrderStages, sellerOrdersStageHref } from '@/lib/seller-order-stages';

interface OrderPipelineCardsProps {
    counts: Record<string, number>;
    activeSlug?: string;
    compact?: boolean;
}

export default function OrderPipelineCards({ counts, activeSlug, compact }: OrderPipelineCardsProps) {
    return (
        <div className={cn('grid gap-3', compact ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2 xl:grid-cols-3')}>
            {sellerOrderStages.map((stage) => {
                const count = counts[stage.countKey] ?? 0;
                const active = activeSlug === stage.slug;
                const Icon = stage.icon;

                return (
                    <Link
                        key={stage.slug}
                        href={sellerOrdersStageHref(stage.slug)}
                        className={cn(
                            'group relative overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md',
                            active ? `border-orange-300 ring-2 ${stage.ring}` : 'border-gray-100 hover:border-orange-200',
                        )}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-gray-500">{stage.label}</p>
                                <p className={cn('mt-1 text-3xl font-bold tabular-nums', count > 0 ? 'text-gray-900' : 'text-gray-300')}>
                                    {count}
                                </p>
                                {!compact && (
                                    <p className="mt-2 line-clamp-2 text-xs text-gray-400">{stage.description}</p>
                                )}
                            </div>
                            <div className={cn('rounded-xl p-3 transition group-hover:scale-105', stage.iconBg)}>
                                <Icon className={cn('h-6 w-6', stage.accent)} strokeWidth={1.75} />
                            </div>
                        </div>
                        {count > 0 && (
                            <span className="absolute right-3 top-3 flex h-2.5 w-2.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-60" />
                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-orange-500" />
                            </span>
                        )}
                        <div className="mt-3 flex items-center text-xs font-medium text-orange-600 opacity-0 transition group-hover:opacity-100">
                            Open queue
                            <ChevronRight className="ml-0.5 h-3.5 w-3.5" />
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}
