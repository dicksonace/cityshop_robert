import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice } from '@/types/marketplace';

interface InvoiceLine {
    product_name?: string;
    seller?: string;
    quantity?: number;
    unit_price?: number;
    total?: number;
}

interface InvoiceShowProps {
    invoice: {
        id: number;
        invoice_number: string;
        type: string;
        line_items: InvoiceLine[];
        subtotal: number;
        commission_amount: number;
        shipping_cost: number;
        total: number;
        payment_method: string | null;
        payment_status: string;
        issued_at: string;
        checkout?: { id: number; checkout_number: string };
        order?: { order_number: string; seller?: { seller_profile?: { business_name?: string }; name?: string } };
    };
}

const typeLabels: Record<string, string> = {
    customer_master: 'Master invoice',
    customer: 'Seller invoice',
};

export default function InvoiceShow({ invoice }: InvoiceShowProps) {
    const sellerName = invoice.order?.seller?.seller_profile?.business_name ?? invoice.order?.seller?.name;

    return (
        <ShopLayout>
            <Head title={`Invoice ${invoice.invoice_number}`} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link
                        href={invoice.checkout ? route('checkouts.show', invoice.checkout.id) : route('orders.index')}
                        className="text-sm text-orange-500 hover:underline"
                    >
                        ← Back to order
                    </Link>
                    <Button type="button" variant="outline" size="sm" onClick={() => window.print()}>
                        <Printer className="mr-2 h-4 w-4" />
                        Print / Save PDF
                    </Button>
                </div>

                <article className="rounded-xl bg-white p-6 shadow-sm print:shadow-none sm:p-8">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-6">
                        <div>
                            <p className="text-2xl font-bold text-gray-900">
                                City<span className="text-orange-500">Shop</span>
                            </p>
                            <p className="mt-1 text-sm text-gray-500">cityunlock.net</p>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-bold text-gray-900">{invoice.invoice_number}</p>
                            <p className="text-sm text-gray-500">{typeLabels[invoice.type] ?? invoice.type}</p>
                            <p className="mt-1 text-sm text-gray-500">
                                {new Date(invoice.issued_at).toLocaleDateString('en-GB', {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                        {invoice.checkout && (
                            <p className="text-gray-600">
                                <span className="font-medium text-gray-900">Checkout:</span> {invoice.checkout.checkout_number}
                            </p>
                        )}
                        {invoice.order && (
                            <p className="text-gray-600">
                                <span className="font-medium text-gray-900">Order:</span> {invoice.order.order_number}
                                {sellerName && <> · {sellerName}</>}
                            </p>
                        )}
                        <p className="text-gray-600 capitalize">
                            <span className="font-medium text-gray-900">Payment:</span> {invoice.payment_status}
                            {invoice.payment_method && <> · {invoice.payment_method.replace(/_/g, ' ')}</>}
                        </p>
                    </div>

                    <table className="mt-8 w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-gray-500">
                                <th className="pb-2 font-medium">Item</th>
                                <th className="pb-2 text-right font-medium">Qty</th>
                                <th className="pb-2 text-right font-medium">Unit</th>
                                <th className="pb-2 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoice.line_items.map((line, i) => (
                                <tr key={i}>
                                    <td className="py-3 pr-2">
                                        <p className="font-medium text-gray-900">{line.product_name}</p>
                                        {line.seller && <p className="text-xs text-gray-400">{line.seller}</p>}
                                    </td>
                                    <td className="py-3 text-right">{line.quantity ?? 1}</td>
                                    <td className="py-3 text-right">{line.unit_price != null ? formatPrice(line.unit_price) : '—'}</td>
                                    <td className="py-3 text-right font-medium">{line.total != null ? formatPrice(line.total) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <div className="mt-6 space-y-2 border-t pt-4 text-sm">
                        <div className="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>{formatPrice(invoice.subtotal)}</span>
                        </div>
                        {invoice.shipping_cost > 0 && (
                            <div className="flex justify-between text-gray-600">
                                <span>Shipping</span>
                                <span>{formatPrice(invoice.shipping_cost)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-lg font-bold text-gray-900">
                            <span>Total</span>
                            <span className="text-orange-500">{formatPrice(invoice.total)}</span>
                        </div>
                    </div>

                    <p className="mt-8 text-center text-xs text-gray-400 print:mt-12">
                        Thank you for shopping on CityShop.
                    </p>
                </article>
            </div>
        </ShopLayout>
    );
}
