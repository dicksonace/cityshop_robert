<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 12mm 12mm 12mm 12mm;
        }
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
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
            width: 186mm;
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
        }
        @endif

        table { border-collapse: collapse; }
        .w-full { width: 100%; }
        .brand {
            font-size: 14pt;
            font-weight: bold;
        }
        .brand span { color: #ea580c; }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            text-align: right;
        }
        .order-number {
            font-size: 10pt;
            font-weight: bold;
            text-align: right;
            margin-top: 2px;
        }
        .muted {
            color: #555;
            font-size: 8pt;
        }
        .right { text-align: right; }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
        .header {
            border-bottom: 1.5pt solid #111;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .box {
            border: 0.6pt solid #ccc;
            padding: 7px 8px;
        }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .badge {
            display: inline-block;
            margin-top: 4px;
            padding: 1px 5px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            background: #ecfdf5;
            color: #047857;
        }
        .badge.cod { background: #ccfbf1; color: #0f766e; }
        .badge.pending { background: #fff7ed; color: #c2410c; }
        .items {
            margin-top: 10px;
            width: 100%;
        }
        .items th {
            font-size: 7pt;
            text-transform: uppercase;
            color: #666;
            text-align: left;
            border-bottom: 1pt solid #111;
            padding: 4px 3px;
        }
        .items td {
            border-bottom: 0.5pt solid #e5e5e5;
            padding: 6px 3px;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .items .num { width: 18px; }
        .items .qty { width: 32px; text-align: right; }
        .items .money { width: 68px; text-align: right; white-space: nowrap; }
        .thumb {
            width: 28px;
            height: 28px;
            border: 0.5pt solid #ddd;
        }
        .thumb-ph {
            width: 28px;
            height: 28px;
            border: 0.5pt solid #ddd;
            background: #f5f5f5;
            text-align: center;
            font-size: 6pt;
            color: #999;
            line-height: 28px;
        }
        .pname { font-weight: bold; font-size: 8.5pt; }
        .pstatus { color: #666; font-size: 7pt; margin-top: 1px; }
        .totals {
            width: 180px;
            margin-top: 8px;
            float: right;
        }
        .totals td {
            padding: 2px 0;
            font-size: 8.5pt;
        }
        .totals td:last-child {
            text-align: right;
            padding-left: 10px;
            white-space: nowrap;
        }
        .totals .grand td {
            border-top: 1.5pt solid #111;
            padding-top: 5px;
            font-size: 10pt;
            font-weight: bold;
        }
        .clear { clear: both; height: 0; }
        .notes {
            margin-top: 18px;
            padding: 7px 8px;
            border: 0.6pt dashed #999;
            background: #fafafa;
            font-size: 8pt;
        }
        .cut {
            margin-top: 16px;
            border-top: 0.6pt dashed #999;
            padding-top: 5px;
            text-align: center;
            font-size: 6.5pt;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .receipt {
            margin-top: 4px;
            font-size: 7.5pt;
        }
        .footer {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 0.5pt solid #ddd;
            font-size: 7pt;
            color: #666;
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
            <td class="top" style="width:50%;">
                <div class="brand">City<span>Shop</span></div>
                <div class="muted">cityunlock.net · Packing slip</div>
                <div class="muted" style="margin-top:4px;"><strong>Store:</strong> {{ $storeName }}</div>
            </td>
            <td class="top right" style="width:50%;">
                <div class="doc-title">Packing slip</div>
                <div class="order-number">{{ $order->order_number }}</div>
                <div class="muted">Placed {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
                <div class="muted">Printed {{ $printedAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="w-full" style="margin-bottom:8px;">
        <tr>
            <td class="top" style="width:49%; padding-right:1%;">
                <div class="box">
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
            <td class="top" style="width:49%; padding-left:1%;">
                <div class="box">
                    <div class="label">Payment &amp; order</div>
                    <strong>{{ $paymentLabel }}</strong><br>
                    <span class="badge {{ $isCod ? 'cod' : ($isPendingPay ? 'pending' : '') }}">
                        {{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}
                    </span>
                    <div class="muted" style="margin-top:6px;">
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
                    <td>
                        <table>
                            <tr>
                                <td class="mid" style="width:34px; padding-right:6px;">
                                    @if($imageSrc)
                                        <img class="thumb" src="{{ $imageSrc }}" width="28" height="28" alt="">
                                    @else
                                        <div class="thumb-ph">—</div>
                                    @endif
                                </td>
                                <td class="mid">
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
    <div class="clear"></div>

    @if(filled($order->delivery_notes))
        <div class="notes">
            <div class="label">Delivery notes</div>
            {{ $order->delivery_notes }}
        </div>
    @endif

    <div class="cut">Customer copy / keep with parcel</div>
    <div class="receipt">
        <strong>{{ $order->order_number }}</strong>
        · {{ $storeName }}
        · {{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}
        · {{ $order->receiver_phone }}
        · {{ implode(', ', $addressLines) }}
    </div>

    <table class="w-full footer">
        <tr>
            <td class="top" style="width:60%;">
                Generated by CityShop for seller fulfillment.<br>
                Not a tax invoice. For packing &amp; delivery use only.
            </td>
            <td class="top right" style="width:40%;">
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
