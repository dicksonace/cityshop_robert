import { Head, Link, router, usePage } from '@inertiajs/react';
import { Package } from 'lucide-react';

import BuyerOrderHub, { OrderHubCounts, OrderStatusTabs, orderStatusMessage } from '@/components/shop/buyer-order-hub';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, Order, OrderItem, Paginated, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface BuyerOrder extends Order {
    checkout_id?: number | null;
    items?: (OrderItem & { product?: { slug: string; images?: { path: string }[] } })[];
    seller?: {
        name: string;
        seller_profile?: { business_name?: string | null; slug?: string };
    };
    checkout?: {
        invoices?: { id: number; invoice_number: string }[];
    };
}

interface OrdersProps {
    orders: Paginated<BuyerOrder>;
    counts: OrderHubCounts;
    tab: string;
}

export default function Orders({ orders, counts, tab }: OrdersProps) {
    const { auth } = usePage<SharedData>().props;

    const handleBuyAgain = (productSlug?: string) => {
        if (!productSlug) return;
        router.visit(route('products.show', productSlug));
    };

    return (
        <ShopLayout>
            <Head title="My Orders" />

            <div className="mx-auto max-w-3xl px-3 py-4 sm:px-4 sm:py-6">
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-lg font-bold text-orange-600">
                        {auth.user?.name?.charAt(0) ?? '?'}
                    </div>
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">{auth.user?.name}</h1>
                        <p className="text-sm text-gray-500">Manage orders</p>
                    </div>
                </div>

                <BuyerOrderHub counts={counts} activeTab={tab} />

                <div className="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div className="px-4 pt-2">
                        <OrderStatusTabs counts={counts} activeTab={tab} />
                    </div>

                    {orders.data.length === 0 ? (
                        <div className="px-4 py-16 text-center">
                            <Package className="mx-auto h-12 w-12 text-gray-300" />
                            <p className="mt-4 font-medium text-gray-900">No orders in this section</p>
                            <p className="mt-1 text-sm text-gray-500">
                                {tab === 'all' ? "You haven't placed any orders yet." : 'Try another tab or start shopping.'}
                            </p>
                            <Link
                                href={route('home')}
                                className="mt-6 inline-block rounded-full bg-orange-500 px-6 py-2 text-sm font-medium text-white hover:bg-orange-600"
                            >
                                Start shopping
                            </Link>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-50">
                            {orders.data.map((order) => {
                                const sellerName =
                                    order.seller?.seller_profile?.business_name ?? order.seller?.name ?? 'Seller';
                                const firstItem = order.items?.[0];
                                const image = firstItem?.product?.images?.[0]?.path;
                                const itemCount = order.items?.reduce((s, i) => s + i.quantity, 0) ?? 0;
                                const productCount = order.items?.length ?? 0;
                                const detailUrl = order.checkout_id
                                    ? route('checkouts.show', order.checkout_id)
                                    : route('orders.show', order.id);
                                const masterInvoice = order.checkout?.invoices?.[0];

                                return (
                                    <article key={order.id} className="p-4">
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <div className="min-w-0">
                                                {order.seller?.seller_profile?.slug ? (
                                                    <Link
                                                        href={route('store.show', order.seller.seller_profile.slug)}
                                                        className="truncate font-semibold text-gray-900 hover:text-orange-500"
                                                    >
                                                        {sellerName}
                                                    </Link>
                                                ) : (
                                                    <p className="truncate font-semibold text-gray-900">{sellerName}</p>
                                                )}
                                                <p className="text-xs text-gray-400">
                                                    {new Date(order.created_at).toLocaleDateString('en-GB', {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                    })}
                                                </p>
                                            </div>
                                            <span className="shrink-0 text-xs font-medium capitalize text-gray-500">
                                                {order.order_number}
                                            </span>
                                        </div>

                                        <Link href={detailUrl} className="mt-3 flex gap-3">
                                            <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gray-50 p-2">
                                                <img
                                                    src={productImageUrl(image)}
                                                    alt=""
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="line-clamp-2 text-sm font-medium text-gray-900">
                                                    {firstItem?.product_name ?? 'Order items'}
                                                </p>
                                                <p className="mt-1 text-xs text-gray-500">
                                                    {productCount} product{productCount !== 1 ? 's' : ''}, {itemCount} item
                                                    {itemCount !== 1 ? 's' : ''}
                                                </p>
                                                <p className="mt-2 text-base font-bold text-gray-900">
                                                    Total {formatPrice(order.total)}
                                                </p>
                                            </div>
                                        </Link>

                                        <p className="mt-3 text-sm text-gray-600">{orderStatusMessage(order)}</p>

                                        {masterInvoice && (
                                            <Link
                                                href={route('invoices.show', masterInvoice.id)}
                                                className="mt-2 inline-block text-xs text-orange-500 hover:underline"
                                            >
                                                Invoice {masterInvoice.invoice_number}
                                            </Link>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {tab === 'confirm' && order.items?.some((i) => i.status === 'awaiting_confirmation') && (
                                                <Button
                                                    size="sm"
                                                    className="bg-green-600 hover:bg-green-700"
                                                    onClick={() => {
                                                        const item = order.items?.find((i) => i.status === 'awaiting_confirmation');
                                                        if (item) {
                                                            router.post(route('orders.confirm-delivery', [order.id, item.id]));
                                                        }
                                                    }}
                                                >
                                                    Confirm delivery
                                                </Button>
                                            )}
                                            {tab === 'completed' && firstItem?.product?.slug ? (
                                                <Button
                                                    size="sm"
                                                    className="w-full bg-orange-500 hover:bg-orange-600 sm:w-auto"
                                                    onClick={() => handleBuyAgain(firstItem.product?.slug)}
                                                >
                                                    Buy again
                                                </Button>
                                            ) : (
                                                <>
                                                    {order.payment_status === 'pending' && order.checkout_id && (
                                                        <Button
                                                            size="sm"
                                                            className="bg-orange-500 hover:bg-orange-600"
                                                            onClick={() => router.visit(route('checkout.payment', order.checkout_id!))}
                                                        >
                                                            Pay now
                                                        </Button>
                                                    )}
                                                    {firstItem?.product?.slug && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="border-orange-200 text-orange-600 hover:bg-orange-50"
                                                            onClick={() => handleBuyAgain(firstItem.product?.slug)}
                                                        >
                                                            Buy again
                                                        </Button>
                                                    )}
                                                </>
                                            )}
                                            <Button size="sm" variant="outline" asChild>
                                                <Link href={detailUrl}>View details</Link>
                                            </Button>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {orders.last_page > 1 && (
                        <div className="flex flex-wrap justify-center gap-2 border-t border-gray-50 p-4">
                            {orders.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active ? 'bg-orange-500 text-white' : 'bg-gray-50 text-gray-600'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
