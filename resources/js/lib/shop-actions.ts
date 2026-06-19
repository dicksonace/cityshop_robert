import { router } from '@inertiajs/react';

interface AddToCartOptions {
    onSuccess?: () => void;
}

export function addProductToCart(productId: number, options?: AddToCartOptions) {
    router.post(
        route('cart.store'),
        { product_id: productId, quantity: 1 },
        {
            preserveScroll: true,
            onSuccess: options?.onSuccess,
        },
    );
}

export function copyToClipboard(text: string): Promise<void> {
    return navigator.clipboard.writeText(text);
}

export function scrollToReviews() {
    document.getElementById('customer-reviews')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
