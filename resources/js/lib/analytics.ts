type AnalyticsIds = {
    ga4?: string | null;
    metaPixel?: string | null;
};

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        fbq?: (...args: unknown[]) => void;
        __cityshopAnalytics?: AnalyticsIds;
    }
}

function ids(): AnalyticsIds {
    return window.__cityshopAnalytics ?? {};
}

export function trackEvent(name: string, params: Record<string, unknown> = {}): void {
    try {
        if (typeof window.gtag === 'function' && ids().ga4) {
            window.gtag('event', name, params);
        }
        if (typeof window.fbq === 'function' && ids().metaPixel) {
            const map: Record<string, string> = {
                view_item: 'ViewContent',
                add_to_cart: 'AddToCart',
                begin_checkout: 'InitiateCheckout',
                purchase: 'Purchase',
                search: 'Search',
            };
            const fbEvent = map[name];
            if (fbEvent) {
                window.fbq('track', fbEvent, params);
            }
        }
    } catch {
        // Tracking must never break shopping.
    }
}

export function trackViewItem(product: {
    id: number;
    name: string;
    price: number;
    category?: string | null;
}): void {
    trackEvent('view_item', {
        currency: 'GHS',
        value: product.price,
        items: [
            {
                item_id: String(product.id),
                item_name: product.name,
                item_category: product.category ?? undefined,
                price: product.price,
                quantity: 1,
            },
        ],
    });
}

export function trackAddToCart(product: {
    id: number;
    name: string;
    price: number;
    quantity?: number;
}): void {
    const qty = product.quantity ?? 1;
    trackEvent('add_to_cart', {
        currency: 'GHS',
        value: product.price * qty,
        items: [
            {
                item_id: String(product.id),
                item_name: product.name,
                price: product.price,
                quantity: qty,
            },
        ],
    });
}

export function trackBeginCheckout(value: number, itemCount: number): void {
    trackEvent('begin_checkout', {
        currency: 'GHS',
        value,
        items: [{ quantity: itemCount }],
    });
}

export function trackPurchase(payload: {
    transactionId: string;
    value: number;
    shipping?: number;
}): void {
    trackEvent('purchase', {
        transaction_id: payload.transactionId,
        currency: 'GHS',
        value: payload.value,
        shipping: payload.shipping ?? 0,
    });
}

export function trackSearch(query: string): void {
    if (!query.trim()) return;
    trackEvent('search', { search_term: query.trim() });
}
