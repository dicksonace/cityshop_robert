import { Head, Link } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, productImageUrl } from '@/types/marketplace';

interface InvoiceLine {
    product_name?: string;
    seller?: string;
    quantity?: number;
    unit_price?: number;
    total?: number;
    image?: string | null;
}

interface SellerContact {
    store_name: string;
    address?: string | null;
    digital_address?: string | null;
    location?: string | null;
    phone?: string | null;
}

interface BuyerShipTo {
    name: string;
    phone?: string | null;
    digital_address?: string | null;
    location?: string | null;
    delivery_notes?: string | null;
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
        order?: { order_number: string; seller?: { seller_profile?: { business_name?: string; store_name?: string }; name?: string } };
    };
    sellerContacts?: SellerContact[];
    sellerContact?: SellerContact | null;
    buyerShipTo?: BuyerShipTo | null;
    deliveryFees?: number;
    shippingFees?: number;
}

const typeLabels: Record<string, string> = {
    customer_master: 'Master invoice',
    customer: 'Seller invoice',
};

function lineImageSrc(image?: string | null): string | null {
    if (!image) {
        return null;
    }
    if (image.startsWith('http') || image.startsWith('/')) {
        return image;
    }

    return productImageUrl(image);
}

export default function InvoiceShow({
    invoice,
    sellerContacts,
    sellerContact,
    buyerShipTo,
    deliveryFees,
    shippingFees,
}: InvoiceShowProps) {
    const contacts =
        sellerContacts && sellerContacts.length > 0
            ? sellerContacts
            : sellerContact
              ? [sellerContact]
              : [];

    const delivery = deliveryFees ?? invoice.shipping_cost ?? 0;
    const shipping = shippingFees ?? invoice.shipping_cost ?? 0;
    const sameDeliveryAndShipping = delivery > 0 && delivery === shipping;

    const sellerName =
        contacts[0]?.store_name
        ?? invoice.order?.seller?.seller_profile?.store_name
        ?? invoice.order?.seller?.seller_profile?.business_name
        ?? invoice.order?.seller?.name;

    const printUrl = route('invoices.print', invoice.id);
    const pdfUrl = route('invoices.pdf', invoice.id);

    return (
        <ShopLayout hideChrome>
            <Head title={`Invoice ${invoice.invoice_number}`} />

            <div className="mx-auto max-w-3xl px-3 pb-28 pt-4 sm:px-4 sm:pb-8 sm:py-8">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 sm:mb-6">
                    <Link
                        href={invoice.checkout ? route('checkouts.show', invoice.checkout.id) : route('orders.index')}
                        className="text-sm text-orange-500 hover:underline"
                    >
                        ← Back to order
                    </Link>
                    <div className="hidden items-center gap-2 sm:flex">
                        <Button asChild size="sm" className="bg-orange-500 text-white hover:bg-orange-600">
                            <a href={printUrl} target="_blank" rel="noopener noreferrer">
                                <Printer className="mr-2 h-4 w-4" />
                                Print
                            </a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <a href={pdfUrl}>
                                <Download className="mr-2 h-4 w-4" />
                                Save PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <article className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:border-0 sm:p-8 sm:shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4">
                        <div>
                            <p className="text-2xl font-bold text-gray-900">
                                City<span className="text-orange-500">Shop</span>
                            </p>
                            <p className="mt-0.5 text-sm text-gray-500">cityunlock.net</p>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-bold text-gray-900">{invoice.invoice_number}</p>
                            <p className="text-sm text-gray-500">{typeLabels[invoice.type] ?? invoice.type}</p>
                            <p className="mt-0.5 text-sm text-gray-500">
                                {new Date(invoice.issued_at).toLocaleDateString('en-GB', {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        {contacts.length > 0 && (
                            <div className="space-y-3">
                                {contacts.map((contact, index) => (
                                    <div
                                        key={`${contact.store_name}-${index}`}
                                        className="h-full rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm"
                                    >
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {contacts.length > 1 ? `Store ${index + 1}` : 'Seller'}
                                        </p>
                                        <dl className="mt-1.5 space-y-1">
                                            <div>
                                                <dt className="text-xs text-gray-500">Store name</dt>
                                                <dd className="font-medium text-gray-900">{contact.store_name}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Phone</dt>
                                                <dd className="text-gray-900">{contact.phone || '—'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Address</dt>
                                                <dd className="text-gray-900">{contact.address || '—'}</dd>
                                            </div>
                                            {contact.digital_address && (
                                                <div>
                                                    <dt className="text-xs text-gray-500">Digital address</dt>
                                                    <dd className="text-gray-900">{contact.digital_address}</dd>
                                                </div>
                                            )}
                                            {contact.location && (
                                                <div>
                                                    <dt className="text-xs text-gray-500">Location</dt>
                                                    <dd className="text-gray-900">{contact.location}</dd>
                                                </div>
                                            )}
                                        </dl>
                                    </div>
                                ))}
                            </div>
                        )}

                        {buyerShipTo && (
                            <div className="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm">
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Ship to (buyer)</p>
                                <dl className="mt-1.5 space-y-1">
                                    <div>
                                        <dt className="text-xs text-gray-500">Name</dt>
                                        <dd className="font-medium text-gray-900">{buyerShipTo.name}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-gray-500">Phone</dt>
                                        <dd className="text-gray-900">{buyerShipTo.phone || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-gray-500">Digital address</dt>
                                        <dd className="text-gray-900">{buyerShipTo.digital_address || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-gray-500">Location</dt>
                                        <dd className="text-gray-900">{buyerShipTo.location || '—'}</dd>
                                    </div>
                                    {buyerShipTo.delivery_notes && (
                                        <div>
                                            <dt className="text-xs text-gray-500">Delivery notes</dt>
                                            <dd className="text-gray-900">{buyerShipTo.delivery_notes}</dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        )}
                    </div>

                    <div className="mt-3 rounded-lg border border-orange-100 bg-orange-50/50 p-3 text-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-orange-700">Delivery &amp; shipping fees</p>
                        <div className="mt-2 space-y-1.5">
                            <div className="flex justify-between gap-3">
                                <span className="text-gray-600">Delivery fees</span>
                                <span className="font-semibold text-gray-900">{formatPrice(delivery)}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-gray-600">Shipping fees</span>
                                <span className="font-semibold text-gray-900">
                                    {sameDeliveryAndShipping ? 'Same as delivery' : formatPrice(shipping)}
                                </span>
                            </div>
                            <p className="text-xs text-gray-500">Included once in the invoice total below.</p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-1 text-sm sm:grid-cols-2">
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

                    <div className="mt-5 overflow-x-auto">
                        <table className="w-full min-w-[520px] table-fixed border-collapse text-sm">
                            <colgroup>
                                <col className="w-[48%]" />
                                <col className="w-[12%]" />
                                <col className="w-[20%]" />
                                <col className="w-[20%]" />
                            </colgroup>
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th className="px-2 py-2.5 font-semibold">Item</th>
                                    <th className="px-2 py-2.5 text-center font-semibold">Qty</th>
                                    <th className="px-2 py-2.5 text-right font-semibold">Unit</th>
                                    <th className="px-2 py-2.5 text-right font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {invoice.line_items.map((line, i) => {
                                    const src = lineImageSrc(line.image);

                                    return (
                                        <tr key={i}>
                                            <td className="px-2 py-3 align-middle">
                                                <div className="flex items-center gap-2.5">
                                                    {src ? (
                                                        <img
                                                            src={src}
                                                            alt=""
                                                            className="h-11 w-11 shrink-0 rounded border border-gray-200 object-contain"
                                                        />
                                                    ) : (
                                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400">
                                                            —
                                                        </div>
                                                    )}
                                                    <div className="min-w-0">
                                                        <p className="font-medium leading-snug text-gray-900">{line.product_name}</p>
                                                        {line.seller && <p className="text-xs text-gray-400">{line.seller}</p>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-2 py-3 text-center align-middle tabular-nums">{line.quantity ?? 1}</td>
                                            <td className="px-2 py-3 text-right align-middle tabular-nums">
                                                {line.unit_price != null ? formatPrice(line.unit_price) : '—'}
                                            </td>
                                            <td className="px-2 py-3 text-right align-middle font-semibold tabular-nums">
                                                {line.total != null ? formatPrice(line.total) : '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 ml-auto w-full max-w-xs space-y-1.5 border-t border-gray-200 pt-3 text-sm">
                        <div className="flex justify-between gap-4 text-gray-600">
                            <span>Subtotal</span>
                            <span className="tabular-nums">{formatPrice(invoice.subtotal)}</span>
                        </div>
                        <div className="flex justify-between gap-4 text-gray-600">
                            <span>Delivery fees</span>
                            <span className="tabular-nums">{formatPrice(delivery)}</span>
                        </div>
                        <div className="flex justify-between gap-4 text-gray-600">
                            <span>Shipping fees</span>
                            <span className="tabular-nums">
                                {sameDeliveryAndShipping ? 'Same as delivery' : formatPrice(shipping)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-gray-100 pt-2 text-base font-bold text-gray-900">
                            <span>Total</span>
                            <span className="tabular-nums text-orange-500">{formatPrice(invoice.total)}</span>
                        </div>
                    </div>

                    <p className="mt-4 text-center text-xs text-gray-400">
                        Thank you for shopping on CityShop.
                    </p>
                </article>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 p-3 backdrop-blur sm:hidden">
                <div className="mx-auto flex max-w-3xl gap-2">
                    <Button asChild className="flex-1 bg-orange-500 text-white hover:bg-orange-600">
                        <a href={printUrl} target="_blank" rel="noopener noreferrer">
                            <Printer className="mr-2 h-4 w-4" />
                            Print
                        </a>
                    </Button>
                    <Button asChild variant="outline" className="flex-1">
                        <a href={pdfUrl}>
                            <Download className="mr-2 h-4 w-4" />
                            Save PDF
                        </a>
                    </Button>
                </div>
            </div>
        </ShopLayout>
    );
}
