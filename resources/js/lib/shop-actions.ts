import { router } from '@inertiajs/react';

interface AddToCartOptions {
    onSuccess?: () => void;
}

/** Prevents double-taps from posting add-to-cart twice in a row. */
const pendingAdds = new Set<number>();

export function addProductToCart(productId: number, options?: AddToCartOptions) {
    if (pendingAdds.has(productId)) {
        return;
    }

    pendingAdds.add(productId);

    router.post(
        route('cart.store'),
        { product_id: productId, quantity: 1 },
        {
            preserveScroll: true,
            onSuccess: options?.onSuccess,
            onFinish: () => {
                // Short cool-down so a bounced second tap does not stack quantity.
                window.setTimeout(() => pendingAdds.delete(productId), 600);
            },
        },
    );
}

export function copyToClipboard(text: string): Promise<void> {
    return navigator.clipboard.writeText(text);
}

export function scrollToReviews() {
    document.getElementById('customer-reviews')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
