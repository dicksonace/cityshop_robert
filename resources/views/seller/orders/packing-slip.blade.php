<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        /* DomPDF-safe layout: avoid mm widths, flex, transform, and margin:auto */
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            background: {{ $mode === 'html' ? '#e5e7eb' : '#ffffff' }};
        }
        @if($mode === 'html')
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #111827;
            color: #fff;
        }
        .toolbar a, .toolbar button {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #111827;
            background: #fff;
        }
        .toolbar .pdf-btn { background: #f97316; color: #fff; }
        .toolbar .muted { color: #9ca3af; font-size: 12px; }
        .sheet {
            width: 210mm;
            max-width: 100%;
            margin: 20px auto;
            padding: 18mm 16mm;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }
        @else
        .sheet {
            width: 100%;
            margin: 0;
            padding: 18pt 16pt;
            background: #fff;
        }
        @endif
        .header {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 10pt;
            margin-bottom: 12pt;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            width: 50%;
        }
        .header-right { text-align: right; }
        .brand {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
        }
        .brand span { color: #ea580c; }
        .doc-title {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .order-number {
            margin: 4pt 0 0;
            font-size: 12pt;
            font-weight: bold;
            font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
            word-break: break-all;
        }
        .meta {
            margin-top: 3pt;
            color: #4b5563;
            font-size: 9pt;
        }
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12pt 0;
        }
        .grid-table > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 6pt 0 0;
        }
        .grid-table > tbody > tr > td + td {
            padding: 0 0 0 6pt;
        }
        .box {
            border: 1px solid #d1d5db;
            padding: 10pt;
        }
        .box-label {
            margin: 0 0 6pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6b7280;
        }
        .box strong { font-size: 11pt; }
        .badge {
            display: inline-block;
            margin-top: 6pt;
            padding: 2pt 6pt;
            background: #ecfdf5;
            color: #047857;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge.cod { background: #ccfbf1; color: #0f766e; }
        .badge.pending { background: #fff7ed; color: #c2410c; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6pt;
            table-layout: fixed;
        }
        table.items th {
            text-align: left;
            font-size: 8pt;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #111827;
            padding: 6pt 4pt;
        }
        table.items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8pt 4pt;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.items .col-num { width: 28pt; }
        table.items .col-qty { width: 40pt; text-align: right; }
        table.items .col-money { width: 70pt; text-align: right; white-space: nowrap; }
        .thumb {
            width: 40pt;
            height: 40pt;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            vertical-align: middle;
        }
        .thumb-placeholder {
            width: 40pt;
            height: 40pt;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            text-align: center;
            color: #9ca3af;
            font-size: 7pt;
            font-weight: bold;
            line-height: 40pt;
        }
        .product-name { font-weight: bold; }
        .product-status { color: #6b7280; font-size: 8pt; margin-top: 2pt; }
        .totals-wrap {
            width: 100%;
            margin-top: 14pt;
        }
        .totals-wrap td { vertical-align: top; }
        .totals {
            width: 220pt;
            border-collapse: collapse;
        }
        .totals tr td {
            padding: 4pt 0;
            font-size: 10pt;
        }
        .totals tr td:last-child {
            text-align: right;
            white-space: nowrap;
            padding-left: 12pt;
        }
        .totals .grand td {
            border-top: 2px solid #111827;
            padding-top: 8pt;
            font-size: 12pt;
            font-weight: bold;
        }
        .notes {
            margin-top: 0;
            padding: 10pt;
            border: 1px dashed #9ca3af;
            background: #f9fafb;
        }
        .after-totals {
            margin-top: 36pt;
            clear: both;
        }
        .footer {
            margin-top: 36pt;
            padding-top: 10pt;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 8pt;
            width: 100%;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            width: 50%;
            vertical-align: top;
        }
        .footer-right { text-align: right; }
        .cut {
            margin: 18pt 0 8pt;
            border-top: 1px dashed #9ca3af;
            text-align: center;
            color: #9ca3af;
            font-size: 7pt;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding-top: 6pt;
        }
        .receipt-mini {
            margin-top: 6pt;
            font-size: 9pt;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                margin: 0;
                padding: 10mm;
                box-shadow: none;
                width: auto;
            }
        }
    </style>
</head>
<body>
@if($mode === 'html')
    <div class="toolbar print:hidden">
        <div>
            <strong>Packing slip</strong>
            <span class="muted"> · {{ $order->order_number }}</span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" onclick="window.print()">Print</button>
            <a class="pdf-btn" href="{{ route('seller.orders.pdf', $focusItemId) }}">Download PDF</a>
            <a href="{{ route('seller.orders.show', $focusItemId) }}">Back to order</a>
        </div>
    </div>
@endif

@php
    $isCod = $order->payment_method === 'cash';
    $isPendingPay = $order->payment_status->value !== 'paid' && ! $isCod;
    $addressLines = array_values(array_filter([
        $order->digital_address,
        $order->city,
        $order->region,
    ]));
@endphp

<div class="sheet">
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <p class="brand">City<span>Shop</span></p>
                    <div class="meta">cityunlock.net · Marketplace packing slip</div>
                    <div class="meta" style="margin-top:6pt;"><strong>Store:</strong> {{ $storeName }}</div>
                </td>
                <td class="header-right">
                    <p class="doc-title">Packing slip</p>
                    <p class="order-number">{{ $order->order_number }}</p>
                    <div class="meta">Placed {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
                    <div class="meta">Printed {{ $printedAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid-table">
        <tr>
            <td>
                <div class="box">
                    <p class="box-label">Ship / deliver to</p>
                    <strong>{{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}</strong><br>
                    @if($order->receiver_phone)
                        Tel: {{ $order->receiver_phone }}<br>
                    @endif
                    @foreach($addressLines as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if($order->buyer?->email)
                        <span style="color:#6b7280;">{{ $order->buyer->email }}</span>
                    @endif
                </div>
            </td>
            <td>
                <div class="box">
                    <p class="box-label">Payment &amp; order</p>
                    <strong>{{ $paymentLabel }}</strong><br>
                    <span class="badge {{ $isCod ? 'cod' : ($isPendingPay ? 'pending' : '') }}">
                        {{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}
                    </span>
                    <div style="margin-top:8pt;color:#4b5563;">
                        Items for this seller: {{ $items->count() }}<br>
                        Qty total: {{ $items->sum('quantity') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th>Product</th>
                <th class="col-qty">Qty</th>
                <th class="col-money">Unit</th>
                <th class="col-money">Line</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                @php $imageSrc = $itemImages[$item->id] ?? null; @endphp
                <tr>
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td>
                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                <td style="width:46pt;vertical-align:middle;padding:0 8pt 0 0;">
                                    @if($imageSrc)
                                        <img class="thumb" src="{{ $imageSrc }}" alt="">
                                    @else
                                        <div class="thumb-placeholder">N/A</div>
                                    @endif
                                </td>
                                <td style="vertical-align:middle;padding:0;">
                                    <div class="product-name">{{ $item->product_name }}</div>
                                    @if($item->status)
                                        <div class="product-status">
                                            Status: {{ str_replace('_', ' ', $item->status->value ?? $item->status) }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-money">GH₵{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="col-money">GH₵{{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width:220pt;">
                <table class="totals">
                    <tr>
                        <td>Items subtotal</td>
                        <td>GH₵{{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if($shipping > 0)
                        <tr>
                            <td>Shipping</td>
                            <td>GH₵{{ number_format($shipping, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>All Total</td>
                        <td>GH₵{{ number_format($allTotal, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="after-totals">
        @if(filled($order->delivery_notes))
            <div class="notes">
                <p class="box-label" style="margin-bottom:4pt;">Delivery notes</p>
                {{ $order->delivery_notes }}
            </div>
        @endif

        <div class="cut" style="margin-top: {{ filled($order->delivery_notes) ? '28pt' : '10pt' }};">
            Customer copy / keep with parcel
        </div>
        <div class="receipt-mini">
            <strong>{{ $order->order_number }}</strong>
            · {{ $storeName }}
            · {{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}
            · {{ $order->receiver_phone }}
            · {{ implode(', ', $addressLines) }}
        </div>
    </div>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    Generated by CityShop for seller fulfillment.<br>
                    Not a tax invoice. For packing &amp; delivery use only.
                </td>
                <td class="footer-right">
                    {{ $storeName }}<br>
                    cityunlock.net
                </td>
            </tr>
        </table>
    </div>
</div>

@if($mode === 'html' && $autoPrint)
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 250);
    });
</script>
@endif
</body>
</html>
