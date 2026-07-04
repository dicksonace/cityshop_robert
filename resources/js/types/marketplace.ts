export interface SellerProfile {
    id: number;
    user_id?: number;
    business_name: string | null;
    store_name: string | null;
    slug: string;
    status: string;
    rating: number;
    total_sales: number;
    store_description?: string | null;
    shop_photo?: string | null;
    business_address?: string | null;
    is_business_registered?: boolean;
    approved_at?: string | null;
    rejection_reason?: string | null;
}

export interface ProductImage {
    id: number;
    path: string;
    is_primary: boolean;
}

export interface Product {
    id: number;
    name: string;
    slug: string;
    description?: string;
    specifications?: Record<string, string> | null;
    price: number;
    discount_price?: number | null;
    quantity: number;
    brand?: string;
    status: string;
    is_preorder: boolean;
    free_shipping: boolean;
    in_ghana: boolean;
    rating: number;
    review_count: number;
    images: ProductImage[];
    seller?: {
        id: number;
        name: string;
        email?: string;
        mobile?: string;
        whatsapp?: string;
        region?: string;
        city?: string;
        digital_address?: string;
        residential_address?: string;
        seller_profile?: SellerProfile;
    };
    category?: { id: number; name: string; slug?: string; icon?: string | null; spec_schema?: { fields: SpecField[] } | null };
}

export interface CartItem {
    id: number;
    quantity: number;
    product: Product;
}

export interface WishlistItem {
    id: number;
    product_id: number;
    product: Product;
    created_at?: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface Wallet {
    available_balance: number;
    pending_balance: number;
    total_earnings: number;
    withdrawn_amount: number;
}

export interface Withdrawal {
    id: number;
    amount: number;
    momo_number: string;
    account_name: string;
    network: string;
    status: string;
    created_at?: string;
}

export interface WalletTransaction {
    id: number;
    type: string;
    amount: number;
    description: string;
    reference?: string | null;
    created_at?: string;
}

export const walletTransactionLabels: Record<string, string> = {
    sale_pending: 'Sale (Pending)',
    sale_released: 'Funds Released',
    withdrawal: 'Withdrawal Request',
    withdrawal_completed: 'Payout Sent',
    withdrawal_refunded: 'Withdrawal Refunded',
    fund_added: 'Funds Added',
    order_payment: 'Order Payment',
    order_refund: 'Order Refund',
    sale_reversed: 'Sale Reversed',
};

export function formatWalletTransactionType(type: string): string {
    return walletTransactionLabels[type] ?? type.replace(/_/g, ' ');
}

export interface Order {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method?: string;
    receiver_name: string;
    receiver_phone: string;
    region: string;
    city: string;
    subtotal: number;
    shipping_cost: number;
    total: number;
    created_at: string;
    items?: OrderItem[];
}

export interface OrderItem {
    id: number;
    product_id?: number;
    product_name: string;
    quantity: number;
    unit_price: number;
    seller_amount: number;
    status: string;
    rejection_reason?: string | null;
    courier_name?: string;
    tracking_number?: string;
    vehicle_number?: string | null;
    driver_phone?: string | null;
    package_image?: string | null;
    product?: Product;
    order?: Order;
}

export interface SpecField {
    key: string;
    label: string;
    type: 'text' | 'select';
    options?: string[];
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    icon?: string | null;
    spec_schema?: { fields: SpecField[] } | null;
}

export interface ProductReview {
    id: number;
    rating: number;
    comment?: string | null;
    created_at?: string;
    user?: { name: string };
}

export function formatPrice(amount: number): string {
    return `GH₵${Number(amount).toFixed(2)}`;
}

export const orderStatusLabels: Record<string, string> = {
    pending: 'Pending',
    processing: 'Processing',
    packed: 'Packed',
    shipped: 'Out for Delivery',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
};

export function formatOrderStatus(status: string): string {
    return orderStatusLabels[status] ?? status.replace(/_/g, ' ');
}

export function productImageUrl(path: string | undefined): string {
    if (!path) return '/images/product-placeholder.svg';
    if (path.startsWith('http') || path.startsWith('blob:')) return path;
    return `/storage/${path}`;
}
