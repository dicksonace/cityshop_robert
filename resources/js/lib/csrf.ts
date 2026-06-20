let cachedToken = '';

function readMetaToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function readXsrfCookie(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function setCsrfToken(token: string): void {
    if (!token) return;
    cachedToken = token;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

export function getCsrfToken(): string {
    return cachedToken || readMetaToken() || readXsrfCookie();
}

export function csrfHeaders(): Record<string, string> {
    const token = getCsrfToken();
    const xsrf = readXsrfCookie();
    const headers: Record<string, string> = {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }
    return headers;
}
