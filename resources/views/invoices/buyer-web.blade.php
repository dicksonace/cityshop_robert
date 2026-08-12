<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #eef0f3;
            color: #111827;
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 10px;
            padding: 12px 14px;
            padding-top: calc(12px + env(safe-area-inset-top, 0px));
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .toolbar a, .toolbar button {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: 0;
            cursor: pointer;
        }
        .toolbar .primary { background: #ea580c; color: #fff; }
        .toolbar .secondary { background: #fff; color: #ea580c; border: 1.5px solid #fdba74; }
        .page {
            width: min(210mm, 100%);
            margin: 0 auto;
            padding: 12px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
        .sheet {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            padding: 22px 18px 28px;
            overflow: hidden;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }
        .brand {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }
        .brand span { color: #ea580c; }
        .site { color: #6b7280; font-size: 12px; margin-top: 4px; }
        .invoice-meta { text-align: right; }
        .invoice-no {
            font-size: 15px;
            font-weight: 800;
            word-break: break-all;
        }
        .muted { color: #6b7280; font-size: 12px; margin-top: 2px; }
        .accent {
            height: 3px;
            background: linear-gradient(90deg, #ea580c, #fb923c);
            margin: 14px 0 16px;
            border-radius: 999px;
        }
        .parties {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            min-width: 0;
        }
        .label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 8px;
        }
        .field { margin: 0 0 5px; font-size: 13px; }
        .field:last-child { margin-bottom: 0; }
        .field .k { color: #6b7280; }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            margin: 16px 0 14px;
            padding: 10px 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            font-size: 12.5px;
            color: #374151;
        }
        .meta strong { color: #111827; }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items thead th {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6b7280;
            text-align: left;
            padding: 10px 8px;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        .items thead th.num,
        .items tbody td.num { text-align: right; }
        .items thead th.qty,
        .items tbody td.qty { text-align: center; width: 11%; }
        .items thead th.img,
        .items tbody td.img { width: 14%; text-align: center; }
        .items thead th.item { width: 42%; }
        .items thead th.unit,
        .items thead th.total { width: 16.5%; }
        .items tbody td {
            padding: 12px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 13px;
        }
        .thumb {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            display: inline-block;
        }
        .thumb-empty {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            color: #9ca3af;
            font-size: 11px;
            background: #fff;
        }
        .item-name { font-weight: 700; color: #111827; word-wrap: break-word; }
        .item-seller { color: #6b7280; font-size: 12px; margin-top: 2px; }
        .totals-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }
        .totals {
            width: min(100%, 280px);
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 5px 0;
            font-size: 13.5px;
            color: #4b5563;
        }
        .totals .grand {
            margin-top: 8px;
            padding-top: 10px;
            border-top: 2px solid #e5e7eb;
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }
        .totals .grand .amount { color: #ea580c; }
        .footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px dashed #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
        }

        @media (min-width: 640px) {
            .page { padding: 24px 16px 40px; }
            .sheet { padding: 32px 36px 40px; }
            .parties { grid-template-columns: 1fr 1fr; gap: 14px; }
            .invoice-no { font-size: 17px; }
            .items thead th.img,
            .items tbody td.img { width: 10%; }
            .items thead th.item { width: 44%; }
        }

        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        @media print {
            html, body {
                background: #fff !important;
                font-size: 11pt;
            }
            .toolbar { display: none !important; }
            .page {
                width: auto;
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .sheet {
                margin: 0;
                padding: 0;
                max-width: none;
                box-shadow: none;
                border-radius: 0;
            }
            .parties {
                grid-template-columns: 1fr 1fr !important;
                gap: 10pt;
            }
            .box {
                background: #f9fafb !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .meta {
                background: #fff7ed !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items thead th {
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items tbody tr { page-break-inside: avoid; }
            .totals-wrap { page-break-inside: avoid; }
            .footer { page-break-inside: avoid; }
            a { color: inherit; text-decoration: none; }
            .thumb, .thumb-empty {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a class="secondary" href="{{ route('invoices.pdf', $invoice) }}">Save PDF</a>
    </div>

    <div class="page">
        <article class="sheet">
            <header class="header">
                <div>
                    <div class="brand">City<span>Shop</span></div>
                    <div class="site">cityunlock.net</div>
                </div>
                <div class="invoice-meta">
                    <div class="invoice-no">{{ $invoice->invoice_number }}</div>
                    <div class="muted">{{ $typeLabel }}</div>
                    <div class="muted">{{ $issuedLabel }}</div>
                </div>
            </header>

            <div class="accent" aria-hidden="true"></div>

            <section class="parties">
                <div>
                    @forelse ($sellerContacts as $index => $contact)
                        <div class="box" @if ($index > 0) style="margin-top: 10px;" @endif>
                            <div class="label">{{ count($sellerContacts) > 1 ? 'Store '.($index + 1) : 'Seller' }}</div>
                            <div class="field"><strong>{{ $contact['store_name'] }}</strong></div>
                            <div class="field"><span class="k">Address:</span> {{ $contact['address'] ?: '—' }}</div>
                            @if (!empty($contact['digital_address']))
                                <div class="field"><span class="k">Digital address:</span> {{ $contact['digital_address'] }}</div>
                            @endif
                            @if (!empty($contact['location']))
                                <div class="field"><span class="k">Location:</span> {{ $contact['location'] }}</div>
                            @endif
                            <div class="field"><span class="k">Phone:</span> {{ $contact['phone'] ?: '—' }}</div>
                        </div>
                    @empty
                        <div class="box">
                            <div class="label">Seller</div>
                            <div class="muted">—</div>
                        </div>
                    @endforelse
                </div>

                <div class="box">
                    <div class="label">Ship to (buyer)</div>
                    <div class="field"><strong>{{ $buyerShipTo['name'] }}</strong></div>
                    <div class="field"><span class="k">Phone:</span> {{ $buyerShipTo['phone'] ?: '—' }}</div>
                    <div class="field"><span class="k">Digital address:</span> {{ $buyerShipTo['digital_address'] ?: '—' }}</div>
                    <div class="field"><span class="k">Location:</span> {{ $buyerShipTo['location'] ?: '—' }}</div>
                    @if (!empty($buyerShipTo['delivery_notes']))
                        <div class="field"><span class="k">Delivery notes:</span> {{ $buyerShipTo['delivery_notes'] }}</div>
                    @endif
                </div>
            </section>

            <div class="meta">
                @if ($invoice->checkout)
                    <span><strong>Checkout:</strong> {{ $invoice->checkout->checkout_number }}</span>
                @endif
                @if ($invoice->order)
                    <span><strong>Order:</strong> {{ $invoice->order->order_number }}</span>
                @endif
                <span>
                    <strong>Payment:</strong>
                    {{ ucfirst(str_replace('_', ' ', (string) $invoice->payment_status)) }}
                    @if ($invoice->payment_method)
                        · {{ str_replace('_', ' ', $invoice->payment_method) }}
                    @endif
                </span>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th class="img">Img</th>
                        <th class="item">Item</th>
                        <th class="qty">Qty</th>
                        <th class="unit num">Unit</th>
                        <th class="total num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineItems as $line)
                        @php
                            $src = $line['image'] ?? null;
                            if (is_string($src) && $src !== '' && ! str_starts_with($src, 'http')) {
                                $src = asset(ltrim($src, '/'));
                            }
                        @endphp
                        <tr>
                            <td class="img">
                                @if ($src)
                                    <img class="thumb" src="{{ $src }}" alt="">
                                @else
                                    <span class="thumb-empty">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="item-name">{{ $line['product_name'] ?? 'Item' }}</div>
                                @if (!empty($line['seller']))
                                    <div class="item-seller">{{ $line['seller'] }}</div>
                                @endif
                            </td>
                            <td class="qty">{{ $line['quantity'] ?? 1 }}</td>
                            <td class="num">
                                @if (isset($line['unit_price']))
                                    GH₵{{ number_format((float) $line['unit_price'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num" style="font-weight: 700;">
                                @if (isset($line['total']))
                                    GH₵{{ number_format((float) $line['total'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-wrap">
                <div class="totals">
                    <div class="row"><span>Subtotal</span><span>GH₵{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
                    <div class="row"><span>Delivery fees</span><span>GH₵{{ number_format((float) $deliveryFees, 2) }}</span></div>
                    <div class="row">
                        <span>Shipping fees</span>
                        <span>
                            @if ((float) $shippingFees > 0 && abs((float) $shippingFees - (float) $deliveryFees) < 0.001)
                                Same as delivery
                            @else
                                GH₵{{ number_format((float) $shippingFees, 2) }}
                            @endif
                        </span>
                    </div>
                    <div class="row grand"><span>Total</span><span class="amount">GH₵{{ number_format((float) $invoice->total, 2) }}</span></div>
                </div>
            </div>

            <footer class="footer">Thank you for shopping on CityShop.</footer>
        </article>
    </div>
</body>
</html>
