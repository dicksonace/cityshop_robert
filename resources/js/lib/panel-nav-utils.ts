import { PanelNavGroup, PanelNavSubItem } from '@/lib/panel-nav-types';

function parseHref(href: string): { pathname: string; params: URLSearchParams } {
    try {
        const url = new URL(href, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
        return { pathname: url.pathname.replace(/\/$/, '') || '/', params: url.searchParams };
    } catch {
        const [pathname = '/', search = ''] = href.split('?');
        return {
            pathname: pathname.replace(/\/$/, '') || '/',
            params: new URLSearchParams(search),
        };
    }
}

function paramsMatch(current: URLSearchParams, target: URLSearchParams): boolean {
    for (const [key, value] of target.entries()) {
        if (current.get(key) !== value) {
            return false;
        }
    }
    return true;
}

export function isNavSubItemActive(currentUrl: string, item: PanelNavSubItem, siblings: PanelNavSubItem[]): boolean {
    const current = parseHref(currentUrl);
    const target = parseHref(item.href);

    if (current.pathname !== target.pathname) {
        return false;
    }

    const targetHasParams = [...target.params.keys()].length > 0;
    const currentHasParams = [...current.params.keys()].length > 0;

    if (targetHasParams) {
        return paramsMatch(current.params, target.params);
    }

    if (currentHasParams) {
        const anotherSiblingMatches = siblings.some((sibling) => {
            if (sibling.key === item.key) {
                return false;
            }
            const siblingTarget = parseHref(sibling.href);
            if (siblingTarget.pathname !== current.pathname) {
                return false;
            }
            return [...siblingTarget.params.keys()].length > 0 && paramsMatch(current.params, siblingTarget.params);
        });
        return !anotherSiblingMatches;
    }

    if (item.defaultOnPath) {
        const anotherDefault = siblings.some((sibling) => sibling.key !== item.key && sibling.defaultOnPath);
        return !anotherDefault || siblings.filter((s) => s.defaultOnPath).length === 1;
    }

    return [...target.params.keys()].length === 0;
}

export function findActiveNavKeys(groups: PanelNavGroup[], currentUrl: string, fallbackSection?: string): { groupKey: string | null; itemKey: string | null } {
    let best: { groupKey: string; itemKey: string; score: number } | null = null;

    for (const group of groups) {
        for (const item of group.items) {
            if (!isNavSubItemActive(currentUrl, item, group.items)) {
                continue;
            }
            const target = parseHref(item.href);
            const score = target.pathname.length + [...target.params.keys()].length * 10;
            if (!best || score > best.score) {
                best = { groupKey: group.key, itemKey: item.key, score };
            }
        }
    }

    if (best) {
        return { groupKey: best.groupKey, itemKey: best.itemKey };
    }

    if (fallbackSection) {
        const group = groups.find((g) => g.key === fallbackSection);
        if (group) {
            return { groupKey: group.key, itemKey: null };
        }
    }

    return { groupKey: null, itemKey: null };
}

export function loadExpandedGroups(storageKey: string, groups: PanelNavGroup[]): Record<string, boolean> {
    if (typeof window === 'undefined') {
        return Object.fromEntries(groups.map((g) => [g.key, g.defaultOpen ?? false]));
    }

    try {
        const raw = localStorage.getItem(storageKey);
        if (!raw) {
            return Object.fromEntries(groups.map((g) => [g.key, g.defaultOpen ?? false]));
        }
        const saved = JSON.parse(raw) as Record<string, boolean>;
        return Object.fromEntries(groups.map((g) => [g.key, saved[g.key] ?? g.defaultOpen ?? false]));
    } catch {
        return Object.fromEntries(groups.map((g) => [g.key, g.defaultOpen ?? false]));
    }
}

export function saveExpandedGroups(storageKey: string, expanded: Record<string, boolean>): void {
    if (typeof window === 'undefined') {
        return;
    }
    localStorage.setItem(storageKey, JSON.stringify(expanded));
}

export function loadPinnedHrefs(storageKey: string): string[] {
    if (typeof window === 'undefined') {
        return [];
    }
    try {
        const raw = localStorage.getItem(storageKey);
        return raw ? (JSON.parse(raw) as string[]) : [];
    } catch {
        return [];
    }
}

export function savePinnedHrefs(storageKey: string, hrefs: string[]): void {
    if (typeof window === 'undefined') {
        return;
    }
    localStorage.setItem(storageKey, JSON.stringify(hrefs));
}

export function flattenNavItems(groups: PanelNavGroup[]): PanelNavSubItem[] {
    return groups.flatMap((group) => group.items);
}

export function filterNavGroups(groups: PanelNavGroup[], query: string): PanelNavGroup[] {
    const q = query.trim().toLowerCase();
    if (!q) {
        return groups;
    }

    return groups
        .map((group) => {
            const groupMatch = group.label.toLowerCase().includes(q);
            const items = group.items.filter(
                (item) => groupMatch || item.label.toLowerCase().includes(q) || group.label.toLowerCase().includes(q),
            );
            return items.length ? { ...group, items, defaultOpen: true } : null;
        })
        .filter((group): group is PanelNavGroup => group !== null);
}
