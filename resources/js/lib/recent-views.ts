const STORAGE_KEY = 'cityshop_recent_views';
const MAX_ITEMS = 20;

export type RecentView = {
    id: number;
    category_id: number | null;
    at: number;
};

function readAll(): RecentView[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) return [];
        return parsed
            .filter((item): item is RecentView => {
                return (
                    !!item &&
                    typeof item === 'object' &&
                    typeof (item as RecentView).id === 'number' &&
                    Number.isFinite((item as RecentView).id)
                );
            })
            .map((item) => ({
                id: item.id,
                category_id: typeof item.category_id === 'number' ? item.category_id : null,
                at: typeof item.at === 'number' ? item.at : Date.now(),
            }));
    } catch {
        return [];
    }
}

function writeAll(items: RecentView[]): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_ITEMS)));
    } catch {
        // Ignore quota / private-mode failures.
    }
}

export function recordRecentView(product: {
    id: number;
    category_id?: number | null;
    category?: { id: number } | null;
}): void {
    if (typeof window === 'undefined' || !product.id) return;

    const categoryId =
        typeof product.category_id === 'number'
            ? product.category_id
            : product.category?.id ?? null;

    const next: RecentView[] = [
        { id: product.id, category_id: categoryId, at: Date.now() },
        ...readAll().filter((item) => item.id !== product.id),
    ];

    writeAll(next);
}

export function getRecentViewIds(): number[] {
    return readAll().map((item) => item.id);
}
