<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        /* DomPDF: keep everything inside printable area — no floats, wrap long lines */
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 14mm 14mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            line-height: 1.28;
            color: #111;
            @if($mode === 'html')
            background: #e5e7eb;
            @else
            background: #fff;
            @endif
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
            font-size: 13px;
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
            width: 182mm;
            max-width: 100%;
            margin: 16px auto;
            padding: 12mm;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; }
        }
        @else
        .sheet {
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        @endif

        table {
            border-collapse: collapse;
            max-width: 100%;
        }
        .w-full {
            width: 100%;
            table-layout: fixed;
        }
        .wrap {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .brand {
            font-size: 13pt;
            font-weight: bold;
        }
        .brand span { color: #ea580c; }
        .doc-title {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            text-align: right;
        }
        .order-number {
            font-size: 9pt;
            font-weight: bold;
            text-align: right;
            margin-top: 2px;
            word-break: break-all;
        }
        .muted {
            color: #555;
            font-size: 7.5pt;
        }
        .right { text-align: right; }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
        .header {
            border-bottom: 1.5pt solid #111;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .box {
            border: 0.6pt solid #ccc;
            padding: 4px 6px;
        }
        .label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 2px;
        }
        .badge {
            display: inline-block;
            margin-top: 2px;
            padding: 1px 5px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            background: #ecfdf5;
            color: #047857;
        }
        .badge.cod { background: #ccfbf1; color: #0f766e; }
        .badge.pending { background: #fff7ed; color: #c2410c; }
        .items {
            margin-top: 6px;
            width: 100%;
            table-layout: fixed;
        }
        .items th {
            font-size: 6.5pt;
            text-transform: uppercase;
            color: #666;
            text-align: left;
            border-bottom: 1pt solid #111;
            padding: 3px 2px;
        }
        .items td {
            border-bottom: 0.5pt solid #e5e5e5;
            padding: 3px 2px;
            font-size: 8pt;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .items .num { width: 16px; }
        .items .qty { width: 28px; text-align: right; }
        .items .money { width: 62px; text-align: right; }
        .thumb {
            width: 26px;
            height: 26px;
            border: 0.5pt solid #ddd;
        }
        .thumb-ph {
            width: 26px;
            height: 26px;
            border: 0.5pt solid #ddd;
            background: #f5f5f5;
            text-align: center;
            font-size: 6pt;
            color: #999;
            line-height: 26px;
        }
        .pname { font-weight: bold; font-size: 8pt; }
        .pstatus { color: #666; font-size: 6.5pt; margin-top: 1px; }
        .totals-wrap {
            width: 100%;
            margin-top: 3px;
        }
        .totals {
            width: 170px;
            margin-left: auto;
        }
        .totals td {
            padding: 1px 0;
            font-size: 8pt;
        }
        .totals td:last-child {
            text-align: right;
            padding-left: 8px;
            white-space: nowrap;
        }
        .totals .grand td {
            border-top: 1.5pt solid #111;
            padding-top: 3px;
            font-size: 9.5pt;
            font-weight: bold;
        }
        .notes {
            margin-top: 8px;
            padding: 4px 6px;
            border: 0.6pt dashed #999;
            background: #fafafa;
            font-size: 7.5pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .cut {
            margin-top: 8px;
            border-top: 0.6pt dashed #999;
            padding-top: 5px;
            text-align: center;
            font-size: 6pt;
            color: #888;
            text-transform: uppercase;
        }
        .receipt {
            margin-top: 6px;
            font-size: 7pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .receipt div {
            margin-top: 2px;
        }
        .footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 0.5pt solid #ddd;
            font-size: 6.5pt;
            color: #666;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .footer td {
            padding: 0 4px 0 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .footer td:last-child {
            padding: 0 0 0 4px;
        }
    </style>
</head>
<body>
@if($mode === 'html')
    <div class="toolbar">
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
    <table class="w-full header">
        <tr>
            <td class="top wrap" style="width:50%;">
                <div class="brand">City<span>Shop</span></div>
                <div class="muted">cityunlock.net · Packing slip</div>
                <div class="muted wrap" style="margin-top:2px;"><strong>Store:</strong> {{ $storeName }}</div>
            </td>
            <td class="top right wrap" style="width:50%;">
                <div class="doc-title">Packing slip</div>
                <div class="order-number">{{ $order->order_number }}</div>
                <div class="muted">Placed {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
                <div class="muted">Printed {{ $printedAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="w-full" style="margin-bottom:5px;">
        <tr>
            <td class="top" style="width:48%; padding-right:2%;">
                <div class="box wrap">
                    <div class="label">Ship / deliver to</div>
                    <strong>{{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}</strong><br>
                    @if($order->receiver_phone)
                        Tel: {{ $order->receiver_phone }}<br>
                    @endif
                    @foreach($addressLines as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if($order->buyer?->email)
                        <span class="muted">{{ $order->buyer->email }}</span>
                    @endif
                </div>
            </td>
            <td class="top" style="width:48%; padding-left:2%;">
                <div class="box wrap">
                    <div class="label">Payment &amp; order</div>
                    <strong>{{ $paymentLabel }}</strong><br>
                    <span class="badge {{ $isCod ? 'cod' : ($isPendingPay ? 'pending' : '') }}">
                        {{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}
                    </span>
                    <div class="muted" style="margin-top:3px;">
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
                <th class="num">#</th>
                <th>Product</th>
                <th class="qty">Qty</th>
                <th class="money">Unit</th>
                <th class="money">Line</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                @php $imageSrc = $itemImages[$item->id] ?? null; @endphp
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td class="wrap">
                        <table style="width:100%; table-layout:fixed;">
                            <tr>
                                <td class="mid" style="width:32px; padding-right:5px;">
                                    @if($imageSrc)
                                        <img class="thumb" src="{{ $imageSrc }}" width="26" height="26" alt="">
                                    @else
                                        <div class="thumb-ph">—</div>
                                    @endif
                                </td>
                                <td class="mid wrap">
                                    <div class="pname">{{ $item->product_name }}</div>
                                    @if($item->status)
                                        <div class="pstatus">Status: {{ str_replace('_', ' ', $item->status->value ?? $item->status) }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="money">GH₵{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="money">GH₵{{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width:170px;">
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

    @if(filled($order->delivery_notes))
        <div class="notes wrap">
            <div class="label">Delivery notes</div>
            {{ $order->delivery_notes }}
        </div>
    @endif

    <div class="cut">Customer copy / keep with parcel</div>
    <div class="receipt wrap">
        <div><strong>{{ $order->order_number }}</strong> · {{ $storeName }}</div>
        <div>
            {{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}
            @if($order->receiver_phone)
                · {{ $order->receiver_phone }}
            @endif
        </div>
        @if(count($addressLines))
            <div>{{ implode(', ', $addressLines) }}</div>
        @endif
    </div>

    <table class="w-full footer">
        <tr>
            <td class="top wrap" style="width:58%;">
                Generated by CityShop for seller fulfillment.<br>
                Not a tax invoice. For packing &amp; delivery use only.
            </td>
            <td class="top right wrap" style="width:42%;">
                {{ $storeName }}<br>
                cityunlock.net
            </td>
        </tr>
    </table>
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
