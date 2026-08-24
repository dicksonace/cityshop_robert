import { csrfHeaders } from '@/lib/csrf';
import type { StatusFeed, StatusItem } from '@/types/status';

async function parseJsonResponse<T>(res: Response): Promise<T> {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const errors = (data as { errors?: Record<string, string[]> }).errors;
        const firstFieldError = errors ? Object.values(errors).flat().find(Boolean) : undefined;
        const message =
            (data as { message?: string }).message ??
            firstFieldError ??
            `Request failed (${res.status})`;
        throw new Error(message);
    }
    return data as T;
}

export async function fetchStatusFeed(): Promise<StatusFeed> {
    const res = await fetch(route('chat.status.index'), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function postStatus(payload: {
    file?: File;
    body?: string;
    backgroundColor?: string;
}): Promise<StatusItem> {
    const form = new FormData();
    if (payload.file) {
        form.append('image', payload.file);
    }
    if (payload.body?.trim()) {
        form.append('body', payload.body.trim());
    }
    if (payload.backgroundColor) {
        form.append('background_color', payload.backgroundColor);
    }

    const res = await fetch(route('chat.status.store'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: form,
    });
    const data = await parseJsonResponse<{ status: StatusItem }>(res);
    return data.status;
}

export async function viewStatus(statusId: number): Promise<void> {
    await fetch(route('chat.status.view', statusId), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
    }).then(parseJsonResponse);
}

export async function deleteStatus(statusId: number): Promise<void> {
    await fetch(route('chat.status.destroy', statusId), {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
    }).then(parseJsonResponse);
}
