import { Head, Link, router, usePage } from '@inertiajs/react';
import { Package } from 'lucide-react';

import BuyerOrderHub, { OrderHubCounts, OrderStatusTabs, orderStatusMessage } from '@/components/shop/buyer-order-hub';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatOrderStatus, formatPrice, Paginated, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface PurchasePackage {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    subtotal: number;
    shipping_cost: number;
    total: number;
    seller_name: string;
    store_slug?: string | null;
    item_count: number;
    product_count: number;
    image?: string | null;
    first_product_name?: string | null;
    awaiting_item_id?: number | null;
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

export default function Orders({ purchases, counts, tab }: OrdersProps) {
    const { auth } = usePage<SharedData>().props;

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
                        <p className="text-sm text-gray-500">Purchases & packages</p>
                    </div>
                </div>

                <BuyerOrderHub counts={counts} activeTab={tab} />

                <div className="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div className="px-4 pt-2">
                        <OrderStatusTabs counts={counts} activeTab={tab} />
                    </div>

                    {purchases.data.length === 0 ? (
                        <div className="px-4 py-16 text-center">
                            <Package className="mx-auto h-12 w-12 text-gray-300" />
                            <p className="mt-4 font-medium text-gray-900">No purchases in this section</p>
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
                            {purchases.data.map((purchase) => {
                                const packageCount = purchase.packages.length;
                                const detailUrl = route('checkouts.show', purchase.id);

                                return (
                                    <article key={purchase.id} className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold text-gray-900">{purchase.checkout_number}</p>
                                                <p className="text-xs text-gray-400">
                                                    {new Date(purchase.created_at).toLocaleDateString('en-GB', {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                    })}
                                                    {' · '}
                                                    {packageCount} package{packageCount === 1 ? '' : 's'}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-base font-bold text-gray-900">{formatPrice(purchase.total)}</p>
                                                <p className="text-xs capitalize text-gray-500">{purchase.payment_status}</p>
                                            </div>
                                        </div>

                                        <div className="mt-3 space-y-2">
                                            {purchase.packages.map((pkg, index) => (
                                                <Link
                                                    key={pkg.id}
                                                    href={route('orders.show', { order: pkg.id, package: 1 })}
                                                    className="flex gap-3 rounded-xl border border-gray-100 bg-gray-50/70 p-3 hover:border-orange-200 hover:bg-orange-50/40"
                                                >
                                                    <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-white p-1.5">
                                                        <img
                                                            src={productImageUrl(pkg.image ?? undefined)}
                                                            alt=""
                                                            className="max-h-full max-w-full object-contain"
                                                        />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <p className="text-xs font-medium text-gray-400">
                                                                    Package {index + 1}
                                                                </p>
                                                                <p className="truncate text-sm font-semibold text-gray-900">
                                                                    {pkg.seller_name}
                                                                </p>
                                                                <p className="mt-0.5 line-clamp-1 text-xs text-gray-500">
                                                                    {pkg.first_product_name}
                                                                    {pkg.product_count > 1
                                                                        ? ` +${pkg.product_count - 1} more`
                                                                        : ''}
                                                                </p>
                                                            </div>
                                                            <span className="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-medium capitalize text-gray-700 ring-1 ring-gray-100">
                                                                {formatOrderStatus(pkg.status)}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 text-xs text-gray-500">
                                                            {orderStatusMessage({
                                                                status: pkg.status,
                                                                payment_status: pkg.payment_status,
                                                            })}
                                                            {pkg.shipping_cost > 0
                                                                ? ` · Delivery ${formatPrice(pkg.shipping_cost)}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>

                                        {purchase.invoice && (
                                            <Link
                                                href={route('invoices.show', purchase.invoice.id)}
                                                className="mt-2 inline-block text-xs text-orange-500 hover:underline"
                                            >
                                                Invoice {purchase.invoice.invoice_number}
                                            </Link>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {tab === 'confirm' &&
                                                purchase.packages
                                                    .filter((pkg) => pkg.awaiting_item_id)
                                                    .map((pkg) => (
                                                        <Button
                                                            key={pkg.id}
                                                            size="sm"
                                                            className="bg-green-600 hover:bg-green-700"
                                                            onClick={() =>
                                                                router.post(
                                                                    route('orders.confirm-delivery', [
                                                                        pkg.id,
                                                                        pkg.awaiting_item_id!,
                                                                    ]),
                                                                )
                                                            }
                                                        >
                                                            Confirm · {pkg.seller_name}
                                                        </Button>
                                                    ))}
                                            {purchase.payment_status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    className="bg-orange-500 hover:bg-orange-600"
                                                    onClick={() => router.visit(route('checkout.payment', purchase.id))}
                                                >
                                                    Pay now
                                                </Button>
                                            )}
                                            <Button size="sm" variant="outline" asChild>
                                                <Link href={detailUrl}>View purchase</Link>
                                            </Button>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {purchases.last_page > 1 && (
                        <div className="flex flex-wrap justify-center gap-2 border-t border-gray-50 p-4">
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
