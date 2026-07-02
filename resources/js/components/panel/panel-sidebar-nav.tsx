import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Pin, PinOff, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { PanelNavCounts, PanelNavGroup, PanelNavSubItem } from '@/lib/panel-nav-types';
import {
    filterNavGroups,
    findActiveNavKeys,
    flattenNavItems,
    isNavSubItemActive,
    loadExpandedGroups,
    loadPinnedHrefs,
    saveExpandedGroups,
    savePinnedHrefs,
} from '@/lib/panel-nav-utils';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

interface PanelSidebarNavProps {
    panelId: string;
    groups: PanelNavGroup[];
    fallbackSection?: string;
    onNavigate?: () => void;
    showSearch?: boolean;
    showPins?: boolean;
    className?: string;
}

function formatBadge(count: number): string {
    return count > 99 ? '99+' : String(count);
}

function resolveBadgeCount(item: PanelNavSubItem, counts: PanelNavCounts, unreadMessages: number): number {
    if (!item.badgeKey) {
        return 0;
    }
    if (item.badgeKey === 'unread_messages') {
        return unreadMessages;
    }
    return counts[item.badgeKey] ?? 0;
}

function NavBadge({ count }: { count: number }) {
    if (count <= 0) {
        return null;
    }
    return (
        <span className="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
            {formatBadge(count)}
        </span>
    );
}

export default function PanelSidebarNav({
    panelId,
    groups,
    fallbackSection,
    onNavigate,
    showSearch = true,
    showPins = true,
    className,
}: PanelSidebarNavProps) {
    const { url, props } = usePage<SharedData>();
    const counts = (props.panelNavCounts as PanelNavCounts | undefined) ?? {};
    const unreadMessages = props.unreadMessages ?? 0;

    const expandedStorageKey = `panel-nav-expanded:${panelId}`;
    const pinsStorageKey = `panel-nav-pins:${panelId}`;

    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<Record<string, boolean>>(() => loadExpandedGroups(expandedStorageKey, groups));
    const [pinnedHrefs, setPinnedHrefs] = useState<string[]>(() => loadPinnedHrefs(pinsStorageKey));

    const { groupKey: activeGroupKey, itemKey: activeItemKey } = useMemo(
        () => findActiveNavKeys(groups, url, fallbackSection),
        [groups, url, fallbackSection],
    );

    const visibleGroups = useMemo(() => filterNavGroups(groups, search), [groups, search]);
    const allItems = useMemo(() => flattenNavItems(groups), [groups]);
    const pinnedItems = useMemo(
        () => pinnedHrefs.map((href) => allItems.find((item) => item.href === href)).filter((item): item is PanelNavSubItem => Boolean(item)),
        [pinnedHrefs, allItems],
    );

    useEffect(() => {
        if (!activeGroupKey) {
            return;
        }
        setExpanded((prev) => {
            if (prev[activeGroupKey]) {
                return prev;
            }
            const next = { ...prev, [activeGroupKey]: true };
            saveExpandedGroups(expandedStorageKey, next);
            return next;
        });
    }, [activeGroupKey, expandedStorageKey]);

    const toggleGroup = (key: string, open: boolean) => {
        setExpanded((prev) => {
            const next = { ...prev, [key]: open };
            saveExpandedGroups(expandedStorageKey, next);
            return next;
        });
    };

    const togglePin = (href: string) => {
        setPinnedHrefs((prev) => {
            const next = prev.includes(href) ? prev.filter((h) => h !== href) : [...prev, href].slice(0, 6);
            savePinnedHrefs(pinsStorageKey, next);
            return next;
        });
    };

    const renderSubItem = (item: PanelNavSubItem, siblings: PanelNavSubItem[], indent = true) => {
        const active = activeItemKey === item.key || isNavSubItemActive(url, item, siblings);
        const badge = resolveBadgeCount(item, counts, unreadMessages);
        const pinned = pinnedHrefs.includes(item.href);

        return (
            <div key={item.key} className="group/item flex items-center gap-0.5">
                <Link
                    href={item.href}
                    onClick={onNavigate}
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-2 rounded-md py-2 text-sm transition-colors',
                        indent ? 'pl-9 pr-2' : 'px-2',
                        active ? 'bg-orange-50 font-medium text-orange-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    )}
                >
                    <span className="truncate">{item.label}</span>
                    <NavBadge count={badge} />
                </Link>
                {showPins && (
                    <button
                        type="button"
                        onClick={() => togglePin(item.href)}
                        className={cn(
                            'rounded p-1 text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 group-hover/item:opacity-100',
                            pinned && 'text-orange-500 opacity-100',
                        )}
                        aria-label={pinned ? 'Unpin from favorites' : 'Pin to favorites'}
                    >
                        {pinned ? <PinOff className="h-3.5 w-3.5" /> : <Pin className="h-3.5 w-3.5" />}
                    </button>
                )}
            </div>
        );
    };

    return (
        <div className={cn('flex flex-col', className)}>
            {showSearch && (
                <div className="border-b border-gray-100 px-3 py-3">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search menu..."
                            className="h-8 border-gray-200 pl-8 text-xs"
                        />
                    </div>
                </div>
            )}

            <nav className="flex-1 space-y-1 overflow-y-auto p-2">
                {showPins && pinnedItems.length > 0 && !search && (
                    <div className="mb-3">
                        <p className="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Pinned</p>
                        <div className="space-y-0.5">
                            {pinnedItems.map((item) => renderSubItem(item, pinnedItems, false))}
                        </div>
                    </div>
                )}

                {visibleGroups.map((group) => {
                    const groupActive = activeGroupKey === group.key;
                    const groupBadge = group.items.reduce((sum, item) => sum + resolveBadgeCount(item, counts, unreadMessages), 0);
                    const isOpen = search ? true : (expanded[group.key] ?? group.defaultOpen ?? false);
                    const singleItem = group.items.length === 1;

                    if (singleItem) {
                        const item = group.items[0];
                        const active = activeItemKey === item.key || isNavSubItemActive(url, item, group.items);
                        const badge = resolveBadgeCount(item, counts, unreadMessages);

                        return (
                            <Link
                                key={group.key}
                                href={item.href}
                                onClick={onNavigate}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                                    active || groupActive ? 'bg-orange-50 text-orange-600' : 'text-gray-700 hover:bg-gray-50',
                                )}
                            >
                                <group.icon className="h-4 w-4 shrink-0" />
                                <span className="truncate">{group.label}</span>
                                <NavBadge count={badge} />
                            </Link>
                        );
                    }

                    return (
                        <Collapsible key={group.key} open={isOpen} onOpenChange={(open) => toggleGroup(group.key, open)}>
                            <CollapsibleTrigger
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors',
                                    groupActive ? 'bg-orange-50/70 text-orange-700' : 'text-gray-700 hover:bg-gray-50',
                                )}
                            >
                                <group.icon className="h-4 w-4 shrink-0" />
                                <span className="flex-1 truncate">{group.label}</span>
                                <NavBadge count={groupBadge} />
                                <ChevronDown className={cn('h-4 w-4 shrink-0 text-gray-400 transition-transform', isOpen && 'rotate-180')} />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="space-y-0.5 pb-1 pt-0.5">
                                {group.items.map((item) => renderSubItem(item, group.items))}
                            </CollapsibleContent>
                        </Collapsible>
                    );
                })}
            </nav>
        </div>
    );
}
