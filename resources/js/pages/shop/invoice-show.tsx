import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';

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
    sellerContact?: {
        store_name: string;
        address?: string | null;
        location?: string | null;
        phone?: string | null;
    } | null;
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

export default function InvoiceShow({ invoice, sellerContact }: InvoiceShowProps) {
    const sellerName =
        sellerContact?.store_name
        ?? invoice.order?.seller?.seller_profile?.business_name
        ?? invoice.order?.seller?.name;

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        if (new URLSearchParams(window.location.search).get('print') !== '1') {
            return;
        }

        let cancelled = false;
        const runPrint = () => {
            if (!cancelled) {
                window.print();
            }
        };

        // Wait for product images so mobile print includes them on page 1.
        const images = Array.from(document.querySelectorAll<HTMLImageElement>('.invoice-sheet img'));
        const pending = images.filter((img) => !img.complete);
        if (pending.length === 0) {
            const timer = window.setTimeout(runPrint, 250);
            return () => {
                cancelled = true;
                window.clearTimeout(timer);
            };
        }

        let left = pending.length;
        const onDone = () => {
            left -= 1;
            if (left <= 0) {
                window.setTimeout(runPrint, 150);
            }
        };
        pending.forEach((img) => {
            img.addEventListener('load', onDone, { once: true });
            img.addEventListener('error', onDone, { once: true });
        });
        const fallback = window.setTimeout(runPrint, 2500);

        return () => {
            cancelled = true;
            window.clearTimeout(fallback);
        };
    }, []);

    return (
        <ShopLayout hideChrome>
            <Head title={`Invoice ${invoice.invoice_number}`}>
                <style>{`
                    @media print {
                        @page {
                            size: A4;
                            margin: 10mm;
                        }
                        html, body {
                            background: #fff !important;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .invoice-sheet {
                            box-shadow: none !important;
                            border-radius: 0 !important;
                            padding: 0 !important;
                            max-width: none !important;
                        }
                    }
                `}</style>
            </Head>

            <div className="mx-auto max-w-3xl px-3 py-4 sm:px-4 sm:py-8 print:max-w-none print:px-0 print:py-0">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden sm:mb-6">
                    <Link
                        href={invoice.checkout ? route('checkouts.show', invoice.checkout.id) : route('orders.index')}
                        className="text-sm text-orange-500 hover:underline"
                    >
                        ← Back to order
                    </Link>
                    <Button
                        type="button"
                        size="sm"
                        className="bg-orange-500 text-white hover:bg-orange-600"
                        onClick={() => window.print()}
                    >
                        <Printer className="mr-2 h-4 w-4" />
                        Print / Save PDF
                    </Button>
                </div>

                <article className="invoice-sheet rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:border-0 sm:p-8 sm:shadow-sm print:border-0 print:p-0 print:shadow-none">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4 print:pb-3">
                        <div>
                            <p className="text-2xl font-bold text-gray-900 print:text-xl">
                                City<span className="text-orange-500">Shop</span>
                            </p>
                            <p className="mt-0.5 text-sm text-gray-500">cityunlock.net</p>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-bold text-gray-900 print:text-base">{invoice.invoice_number}</p>
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

                    {sellerContact && (
                        <div className="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm print:mt-3 print:p-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Seller</p>
                            <dl className="mt-1.5 grid gap-1 sm:grid-cols-2 print:gap-0.5">
                                <div>
                                    <dt className="text-xs text-gray-500">Store name</dt>
                                    <dd className="font-medium text-gray-900">{sellerContact.store_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500">Phone</dt>
                                    <dd className="text-gray-900">{sellerContact.phone || '—'}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs text-gray-500">Address</dt>
                                    <dd className="text-gray-900">{sellerContact.address || '—'}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs text-gray-500">Location</dt>
                                    <dd className="text-gray-900">{sellerContact.location || '—'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    <div className="mt-4 grid gap-1 text-sm sm:grid-cols-2 print:mt-3">
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

                    <table className="mt-5 w-full text-sm print:mt-4">
                        <thead>
                            <tr className="border-b text-left text-gray-500">
                                <th className="pb-2 font-medium">Item</th>
                                <th className="pb-2 text-center font-medium">Qty</th>
                                <th className="pb-2 text-right font-medium">Unit</th>
                                <th className="pb-2 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoice.line_items.map((line, i) => {
                                const src = lineImageSrc(line.image);

                                return (
                                    <tr key={i}>
                                        <td className="py-2.5 pr-2 print:py-1.5">
                                            <div className="flex items-center gap-2.5">
                                                {src ? (
                                                    <img
                                                        src={src}
                                                        alt=""
                                                        className="h-12 w-12 shrink-0 rounded border border-gray-200 object-contain print:h-10 print:w-10"
                                                    />
                                                ) : (
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400 print:h-10 print:w-10">
                                                        —
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="font-medium text-gray-900">{line.product_name}</p>
                                                    {line.seller && <p className="text-xs text-gray-400">{line.seller}</p>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="py-2.5 text-center print:py-1.5">{line.quantity ?? 1}</td>
                                        <td className="py-2.5 text-right print:py-1.5">
                                            {line.unit_price != null ? formatPrice(line.unit_price) : '—'}
                                        </td>
                                        <td className="py-2.5 text-right font-medium print:py-1.5">
                                            {line.total != null ? formatPrice(line.total) : '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <div className="mt-4 ml-auto max-w-xs space-y-1.5 border-t pt-3 text-sm print:mt-3">
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
                        <div className="flex justify-between text-base font-bold text-gray-900">
                            <span>Total</span>
                            <span className="text-orange-500">{formatPrice(invoice.total)}</span>
                        </div>
                    </div>

                    <p className="mt-4 text-center text-xs text-gray-400 print:mt-3">
                        Thank you for shopping on CityShop.
                    </p>
                </article>
            </div>
        </ShopLayout>
    );
}
