const STORAGE_KEY = 'cityshop_checkout_draft';

export type CheckoutDraftSellerPayment = {
    channel: string;
    method_id?: number;
};

export type CheckoutDraft = {
    cartKey: string;
    address_id: number | null;
    payment_method: string;
    seller_payments: Record<string, CheckoutDraftSellerPayment>;
    seller_coupons: Record<string, string>;
};

export function checkoutCartKey(
    sellerGroups: { seller_id: number; items: { id: number; quantity: number }[] }[],
): string {
    return sellerGroups
        .map((group) => `${group.seller_id}:${group.items.map((item) => `${item.id}x${item.quantity}`).join(',')}`)
        .join('|');
}

export function loadCheckoutDraft(cartKey: string): CheckoutDraft | null {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as Partial<CheckoutDraft>;
        if (!parsed || parsed.cartKey !== cartKey) return null;
        return {
            cartKey,
            address_id: typeof parsed.address_id === 'number' ? parsed.address_id : null,
            payment_method: typeof parsed.payment_method === 'string' ? parsed.payment_method : 'momo',
            seller_payments:
                parsed.seller_payments && typeof parsed.seller_payments === 'object'
                    ? parsed.seller_payments
                    : {},
            seller_coupons:
                parsed.seller_coupons && typeof parsed.seller_coupons === 'object'
                    ? parsed.seller_coupons
                    : {},
        };
    } catch {
        return null;
    }
}

export function saveCheckoutDraft(draft: CheckoutDraft): void {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    } catch {
        // ignore quota / private mode
    }
}

export function clearCheckoutDraft(): void {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // ignore
    }
}

const PAYMENT_REF_PREFIX = 'cityshop_payment_ref_';

export function loadPaymentReference(orderId: number): string | null {
    try {
        return sessionStorage.getItem(`${PAYMENT_REF_PREFIX}${orderId}`);
    } catch {
        return null;
    }
}

export function savePaymentReference(orderId: number, reference: string): void {
    try {
        const key = `${PAYMENT_REF_PREFIX}${orderId}`;
        if (reference.trim() === '') {
            sessionStorage.removeItem(key);
        } else {
            sessionStorage.setItem(key, reference);
        }
    } catch {
        // ignore
    }
}

export function clearPaymentReference(orderId: number): void {
    try {
        sessionStorage.removeItem(`${PAYMENT_REF_PREFIX}${orderId}`);
    } catch {
        // ignore
    }
}
