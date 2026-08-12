import { csrfHeaders } from '@/lib/csrf';

export async function recordProductVideoPlay(slug: string): Promise<number | null> {
    try {
        const res = await fetch(route('products.video-play', slug), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            return null;
        }

        const data = (await res.json()) as { video_plays?: number };
        return typeof data.video_plays === 'number' ? data.video_plays : null;
    } catch {
        return null;
    }
}
