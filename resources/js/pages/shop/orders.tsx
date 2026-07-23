import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Package, Store, Truck } from 'lucide-react';

import BuyerOrderHub, { OrderHubCounts, OrderStatusTabs, orderStatusMessage } from '@/components/shop/buyer-order-hub';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, orderStatusBadgeClass, Paginated, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface PurchasePackage {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method?: string | null;
    subtotal: number;
    shipping_cost: number;
    total: number;
    seller_name: string;
    store_slug?: string | null;
    item_count: number;
    product_count: number;
    image?: string | null;
    first_product_name?: string | null;
    first_product_slug?: string | null;
    awaiting_item_id?: number | null;
    needs_review?: boolean;
    can_refund?: boolean;
    driver_phone?: string | null;
    vehicle_number?: string | null;
}

interface Purchase {
    id: number;
    checkout_number: string;
    payment_status: string;
    status: string;
    subtotal: number;
    shipping_cost: number;
    total: number;
    created_at: string;
    invoice?: { id: number; invoice_number: string } | null;
    packages: PurchasePackage[];
}

interface OrdersProps {
    purchases: Paginated<Purchase>;
    counts: OrderHubCounts;
    tab: string;
}

function packageHeadline(pkg: PurchasePackage): string {
    if (pkg.status === 'cancelled') return 'Order closed';
    if (pkg.payment_status === 'pending' && pkg.payment_method !== 'cash') return 'Awaiting payment';
    if (pkg.status === 'delivered') return 'Order completed';
    if (pkg.status === 'awaiting_confirmation') return 'Confirm delivery';
    if (pkg.status === 'shipped') return 'Out for delivery';
    if (pkg.status === 'refunded' || pkg.payment_status === 'refunded') return 'Refund / after-sales';
    if (pkg.status === 'packed') return 'Packing';
    if (pkg.status === 'call_confirmed') return 'Seller called';
    if (pkg.status === 'processing') return 'Processing';
    if (pkg.payment_method === 'cash') return 'Cash on delivery';
    return 'Processing';
}

export default function Orders({ purchases, counts, tab }: OrdersProps) {
    usePage<SharedData>();

    const packages = purchases.data.flatMap((purchase) =>
        purchase.packages.map((pkg) => ({ purchase, pkg })),
    );

    return (
        <ShopLayout>
            <Head title="Manage orders" />

            <div className="mx-auto max-w-3xl bg-gray-100/80 px-0 pb-6 sm:px-4 sm:py-6">
                <div className="bg-white px-4 py-3 sm:rounded-t-2xl sm:px-4">
                    <h1 className="text-center text-base font-semibold text-gray-900 sm:text-left sm:text-lg">
                        Manage orders
                    </h1>
                </div>

                <div className="bg-white px-3 pb-3 sm:px-4">
                    <BuyerOrderHub counts={counts} activeTab={tab} />
                </div>

                <div className="mt-2 bg-white sm:mt-4 sm:overflow-hidden sm:rounded-2xl sm:ring-1 sm:ring-gray-100">
                    <div className="px-2 pt-1 sm:px-4 sm:pt-2">
                        <OrderStatusTabs counts={counts} activeTab={tab} />
                    </div>

                    {packages.length === 0 ? (
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
                        <div className="space-y-3 bg-gray-100/80 p-3 sm:bg-transparent sm:p-4">
                            {packages.map(({ purchase, pkg }) => {
                                const detailUrl = route('orders.show', { order: pkg.id, package: 1 });
                                const purchaseUrl = route('checkouts.show', purchase.id);
                                const storeUrl = pkg.store_slug ? route('store.show', pkg.store_slug) : null;
                                const statusLine = orderStatusMessage({
                                    status: pkg.status,
                                    payment_status: pkg.payment_status,
                                    payment_method: pkg.payment_method,
                                    items: [
                                        {
                                            status: pkg.status,
                                            driver_phone: pkg.driver_phone,
                                            vehicle_number: pkg.vehicle_number,
                                        },
                                    ],
                                });

                                return (
                                    <article
                                        key={`${purchase.id}-${pkg.id}`}
                                        className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100"
                                    >
                                        <div className="flex items-center justify-between gap-2 border-b border-gray-50 px-3 py-2.5">
                                            {storeUrl ? (
                                                <Link
                                                    href={storeUrl}
                                                    className="flex min-w-0 flex-1 items-center gap-1.5 text-sm font-medium text-gray-900"
                                                >
                                                    <Store className="h-4 w-4 shrink-0 text-gray-400" />
                                                    <span className="truncate">{pkg.seller_name}</span>
                                                </Link>
                                            ) : (
                                                <div className="flex min-w-0 flex-1 items-center gap-1.5 text-sm font-medium text-gray-900">
                                                    <Store className="h-4 w-4 shrink-0 text-gray-400" />
                                                    <span className="truncate">{pkg.seller_name}</span>
                                                </div>
                                            )}
                                            <div className="flex shrink-0 items-center gap-2">
                                                {storeUrl && (
                                                    <Link
                                                        href={storeUrl}
                                                        className="inline-flex items-center gap-0.5 rounded-full bg-orange-50 px-2 py-1 text-[11px] font-semibold text-orange-600 ring-1 ring-orange-100"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        Visit
                                                        <ChevronRight className="h-3 w-3" />
                                                    </Link>
                                                )}
                                                <span className="text-xs font-medium text-gray-500">
                                                    {packageHeadline(pkg)}
                                                </span>
                                            </div>
                                        </div>

                                        <Link href={detailUrl} className="flex gap-3 px-3 py-3">
                                            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-gray-50 p-1">
                                                <img
                                                    src={productImageUrl(pkg.image ?? undefined)}
                                                    alt=""
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="line-clamp-2 text-sm text-gray-800">
                                                    {pkg.first_product_name ?? 'Order items'}
                                                </p>
                                                <p className="mt-1 text-xs text-gray-400">
                                                    {pkg.product_count} product{pkg.product_count === 1 ? '' : 's'},{' '}
                                                    {pkg.item_count} item{pkg.item_count === 1 ? '' : 's'}
                                                </p>
                                                {pkg.payment_status === 'paid' && pkg.status !== 'cancelled' && (
                                                    <p className="mt-1.5 text-xs font-medium text-emerald-600">
                                                        Buyer protection · Secured payment
                                                    </p>
                                                )}
                                            </div>
                                        </Link>

                                        <Link
                                            href={detailUrl}
                                            className={`mx-3 mb-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium ${orderStatusBadgeClass(pkg.status)}`}
                                        >
                                            <Truck className="h-3.5 w-3.5 shrink-0 opacity-70" />
                                            <span className="min-w-0 flex-1 truncate">{statusLine}</span>
                                            <ChevronRight className="h-3.5 w-3.5 shrink-0 opacity-60" />
                                        </Link>

                                        <div className="flex items-center justify-between gap-2 px-3 pb-1 text-xs text-gray-400">
                                            <span className="truncate">{purchase.checkout_number}</span>
                                            {purchase.invoice && (
                                                <Link
                                                    href={route('invoices.show', purchase.invoice.id)}
                                                    className="shrink-0 text-orange-500 hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    Invoice
                                                </Link>
                                            )}
                                        </div>

                                        <div className="flex items-baseline justify-end gap-1 px-3 py-2">
                                            <span className="text-xs text-gray-500">Total</span>
                                            <span className="text-base font-bold text-gray-900">
                                                {formatPrice(pkg.total)}
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap justify-end gap-2 border-t border-gray-50 px-3 py-3">
                                            {pkg.payment_status === 'pending'
                                                && pkg.payment_method !== 'cash'
                                                && pkg.status !== 'cancelled'
                                                && purchase.status !== 'cancelled' && (
                                                <Button
                                                    size="sm"
                                                    className="rounded-full bg-orange-500 px-4 hover:bg-orange-600"
                                                    onClick={() => router.visit(route('checkout.payment', purchase.id))}
                                                >
                                                    Pay now
                                                </Button>
                                            )}

                                            {pkg.awaiting_item_id && (
                                                <Button
                                                    size="sm"
                                                    className="rounded-full bg-orange-500 px-4 hover:bg-orange-600"
                                                    onClick={() =>
                                                        router.post(
                                                            route('orders.confirm-delivery', [
                                                                pkg.id,
                                                                pkg.awaiting_item_id!,
                                                            ]),
                                                        )
                                                    }
                                                >
                                                    Confirm delivery
                                                </Button>
                                            )}

                                            {pkg.can_refund && (
                                                <Button size="sm" variant="outline" className="rounded-full" asChild>
                                                    <Link href={detailUrl}>Apply for refund</Link>
                                                </Button>
                                            )}

                                            {pkg.first_product_slug && (
                                                <Button size="sm" variant="outline" className="rounded-full" asChild>
                                                    <Link href={route('products.show', pkg.first_product_slug)}>
                                                        Buy again
                                                    </Link>
                                                </Button>
                                            )}

                                            {pkg.needs_review && (
                                                <Button
                                                    size="sm"
                                                    className="rounded-full bg-orange-500 px-4 hover:bg-orange-600"
                                                    asChild
                                                >
                                                    <Link href={detailUrl}>Write review</Link>
                                                </Button>
                                            )}

                                            {!pkg.awaiting_item_id
                                                && pkg.payment_status !== 'pending'
                                                && !pkg.needs_review && (
                                                <Button size="sm" variant="outline" className="rounded-full" asChild>
                                                    <Link href={purchaseUrl}>View details</Link>
                                                </Button>
                                            )}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {purchases.last_page > 1 && (
                        <div className="flex flex-wrap justify-center gap-2 bg-white p-4">
                            {purchases.links.map((link, i) => (
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
