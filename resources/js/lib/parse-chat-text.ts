export type ChatTextKind = 'plain' | 'url' | 'phone';

export type ChatTextSegment = {
    text: string;
    kind: ChatTextKind;
};

export type CityShopDeepLink = {
    kind: 'product' | 'store' | 'live';
    slug: string;
    path: string;
};

const urlPattern = /(?:https?:\/\/[^\s<>"\]]+|www\.[^\s<>"\]]+|cityshop:\/\/[^\s<>"\]]+)/gi;
const phonePattern = /(?:\+233|233|0)[\s-]*\d(?:[\s-]*\d){8}/g;

function trimTrailingPunctuation(value: string): string {
    return value.replace(/[.,;:!?)\]]+$/, '');
}

function isCityShopHost(host: string): boolean {
    const h = host.toLowerCase().replace(/^www\./, '');
    return h === 'cityunlock.net' || h === 'localhost' || h === '127.0.0.1' || h === '10.0.2.2';
}

export function parseCityShopDeepLink(raw: string): CityShopDeepLink | null {
    let value = raw.trim();
    if (value.startsWith('www.')) {
        value = `https://${value}`;
    }

    let uri: URL;
    try {
        uri = new URL(value);
    } catch {
        return null;
    }

    let path: string;
    if (uri.protocol === 'cityshop:') {
        path = uri.hostname === 'app' ? uri.pathname : `/${uri.hostname}${uri.pathname}`;
    } else {
        if (!isCityShopHost(uri.hostname)) {
            return null;
        }
        path = uri.pathname;
    }

    if (path.startsWith('/app/')) {
        path = path.slice(4);
    }
    if (path.startsWith('/product/') && !path.startsWith('/products/')) {
        path = `/products/${path.slice('/product/'.length)}`;
    } else if (path.startsWith('/store/') && !path.startsWith('/stores/')) {
        path = `/stores/${path.slice('/store/'.length)}`;
    }

    path = path.replace(/\/$/, '');

    const product = path.match(/^\/products\/([^/]+)$/);
    if (product) return { kind: 'product', slug: product[1], path };
    const store = path.match(/^\/stores\/([^/]+)$/);
    if (store) return { kind: 'store', slug: store[1], path };
    const live = path.match(/^\/live\/([^/]+)$/);
    if (live) return { kind: 'live', slug: live[1], path };
    return null;
}

export function firstCityShopLinkIn(text: string): CityShopDeepLink | null {
    const matches = text.match(urlPattern) ?? [];
    for (const match of matches) {
        const link = parseCityShopDeepLink(trimTrailingPunctuation(match));
        if (link) return link;
    }
    return null;
}

export function parseChatText(text: string): ChatTextSegment[] {
    if (!text) return [];

    const occupied: Array<{ start: number; end: number }> = [];
    const found: Array<{ start: number; end: number; kind: ChatTextKind }> = [];

    for (const match of text.matchAll(urlPattern)) {
        const raw = match[0];
        const trimmed = trimTrailingPunctuation(raw);
        const start = match.index ?? 0;
        const end = start + trimmed.length;
        occupied.push({ start, end });
        found.push({ start, end, kind: 'url' });
    }

    for (const match of text.matchAll(phonePattern)) {
        const start = match.index ?? 0;
        const end = start + match[0].length;
        if (occupied.some((range) => start < range.end && end > range.start)) {
            continue;
        }
        if (start > 0 && /\d/.test(text[start - 1] ?? '')) {
            continue;
        }
        occupied.push({ start, end });
        found.push({ start, end, kind: 'phone' });
    }

    found.sort((a, b) => a.start - b.start);

    const segments: ChatTextSegment[] = [];
    let cursor = 0;
    for (const item of found) {
        if (item.start > cursor) {
            segments.push({ text: text.slice(cursor, item.start), kind: 'plain' });
        }
        segments.push({ text: text.slice(item.start, item.end), kind: item.kind });
        cursor = item.end;
    }
    if (cursor < text.length) {
        segments.push({ text: text.slice(cursor), kind: 'plain' });
    }
    return segments;
}
