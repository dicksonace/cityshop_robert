<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        /* Industry packing-slip layout — works for browser print and DomPDF */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            background: {{ $mode === 'html' ? '#e5e7eb' : '#ffffff' }};
        }
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
            margin: {{ $mode === 'html' ? '20px auto' : '0' }};
            padding: 18mm 16mm;
            background: #fff;
            @if($mode === 'html')
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
            @endif
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right { text-align: right; }
        .brand {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .brand span { color: #ea580c; }
        .doc-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .order-number {
            margin: 6px 0 0;
            font-size: 16px;
            font-weight: 800;
            font-family: DejaVu Sans Mono, Consolas, monospace;
            letter-spacing: 0.04em;
        }
        .meta {
            margin-top: 4px;
            color: #4b5563;
            font-size: 11px;
        }
        .grid {
            display: table;
            width: 100%;
            margin: 18px 0;
        }
        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .col + .col { padding-right: 0; padding-left: 12px; }
        .box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px;
            min-height: 110px;
        }
        .box-label {
            margin: 0 0 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .box strong { font-size: 13px; }
        .badge {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #047857;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge.cod { background: #ccfbf1; color: #0f766e; }
        .badge.pending { background: #fff7ed; color: #c2410c; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.items th {
            text-align: left;
            font-size: 10px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #111827;
            padding: 8px 6px;
        }
        table.items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 6px;
            vertical-align: top;
        }
        table.items .qty, table.items .money {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        table.items .qty { width: 56px; }
        table.items .money { width: 90px; }
        .totals {
            width: 260px;
            margin: 14px 0 0 auto;
        }
        .totals tr td {
            padding: 4px 0;
        }
        .totals tr td:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .totals .grand td {
            border-top: 2px solid #111827;
            padding-top: 8px;
            font-size: 14px;
            font-weight: 800;
        }
        .notes {
            margin-top: 18px;
            padding: 12px;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            background: #f9fafb;
        }
        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 10px;
            display: table;
            width: 100%;
        }
        .footer-left, .footer-right {
            display: table-cell;
            width: 50%;
        }
        .footer-right { text-align: right; }
        .cut {
            margin: 22px 0 10px;
            border-top: 1px dashed #9ca3af;
            position: relative;
        }
        .cut span {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 0 8px;
            color: #9ca3af;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .receipt-mini {
            margin-top: 8px;
            font-size: 11px;
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
        <div class="header-left">
            <p class="brand">City<span>Shop</span></p>
            <div class="meta">cityunlock.net · Marketplace packing slip</div>
            <div class="meta" style="margin-top:8px;"><strong>Store:</strong> {{ $storeName }}</div>
        </div>
        <div class="header-right">
            <p class="doc-title">Packing slip</p>
            <p class="order-number">{{ $order->order_number }}</p>
            <div class="meta">Placed {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
            <div class="meta">Printed {{ $printedAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="col">
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
        </div>
        <div class="col">
            <div class="box">
                <p class="box-label">Payment &amp; order</p>
                <strong>{{ $paymentLabel }}</strong><br>
                <span class="badge {{ $isCod ? 'cod' : ($isPendingPay ? 'pending' : '') }}">
                    {{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}
                </span>
                <div style="margin-top:10px;color:#4b5563;">
                    Items for this seller: {{ $items->count() }}<br>
                    Qty total: {{ $items->sum('quantity') }}
                </div>
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th>Product</th>
                <th class="qty">Qty</th>
                <th class="money">Unit</th>
                <th class="money">Line</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong>
                        @if($item->status)
                            <div style="color:#6b7280;font-size:10px;margin-top:2px;">
                                Status: {{ str_replace('_', ' ', $item->status->value ?? $item->status) }}
                            </div>
                        @endif
                    </td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="money">GH₵{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="money">GH₵{{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Items subtotal</td>
            <td>GH₵{{ number_format($subtotal, 2) }}</td>
        </tr>
        @if((float) $order->shipping_cost > 0)
            <tr>
                <td>Shipping (order)</td>
                <td>GH₵{{ number_format((float) $order->shipping_cost, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Seller lines total</td>
            <td>GH₵{{ number_format($subtotal, 2) }}</td>
        </tr>
    </table>

    @if(filled($order->delivery_notes))
        <div class="notes">
            <p class="box-label" style="margin-bottom:4px;">Delivery notes</p>
            {{ $order->delivery_notes }}
        </div>
    @endif

    <div class="cut"><span>Customer copy / keep with parcel</span></div>
    <div class="receipt-mini">
        <strong>{{ $order->order_number }}</strong>
        · {{ $storeName }}
        · {{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}
        · {{ $order->receiver_phone }}
        · {{ implode(', ', $addressLines) }}
    </div>

    <div class="footer">
        <div class="footer-left">
            Generated by CityShop for seller fulfillment.<br>
            Not a tax invoice. For packing &amp; delivery use only.
        </div>
        <div class="footer-right">
            {{ $storeName }}<br>
            cityunlock.net
        </div>
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
