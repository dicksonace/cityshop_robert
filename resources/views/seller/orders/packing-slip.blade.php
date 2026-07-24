<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 9pt;
            color: #111111;
            line-height: 1.35;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .wrap {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #555555; font-size: 7.5pt; }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666666;
            letter-spacing: 0.3pt;
            margin-bottom: 3pt;
        }
        .brand {
            font-size: 16pt;
            font-weight: bold;
            line-height: 1.1;
        }
        .brand span { color: #ea580c; }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
        }
        .order-no {
            font-size: 9.5pt;
            font-weight: bold;
            margin-top: 1pt;
        }
        .accent {
            height: 3pt;
            background: #ea580c;
            border: 0;
            margin: 6pt 0 8pt 0;
        }
        .box {
            border: 0.6pt solid #cccccc;
            padding: 6pt 7pt;
            background-color: #fafafa;
        }
        .field {
            margin-bottom: 2.5pt;
            font-size: 8.5pt;
        }
        .field .k {
            color: #666666;
            font-size: 7.5pt;
            display: inline;
        }
        .meta {
            margin-bottom: 8pt;
            font-size: 8pt;
        }
        .pill {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #047857;
            background-color: #ecfdf5;
            padding: 1pt 5pt;
        }
        .items th {
            font-size: 7pt;
            text-transform: uppercase;
            color: #666666;
            border-bottom: 1pt solid #111111;
            padding: 4pt 3pt;
            vertical-align: bottom;
        }
        .items td {
            border-bottom: 0.5pt solid #e5e5e5;
            padding: 6pt 3pt;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .items th.right,
        .items td.right {
            text-align: right;
        }
        .items th.center,
        .items td.center {
            text-align: center;
        }
        .thumb {
            width: 36pt;
            height: 36pt;
            border: 0.5pt solid #dddddd;
        }
        .pname {
            font-weight: bold;
            font-size: 9pt;
            line-height: 1.25;
        }
        .pstatus {
            color: #666666;
            font-size: 7pt;
            margin-top: 1pt;
        }
        .totals {
            width: 200pt;
            margin-top: 6pt;
        }
        .totals td {
            padding: 2pt 0;
            font-size: 8.5pt;
        }
        .totals .amt {
            text-align: right;
            width: 90pt;
            white-space: nowrap;
        }
        .totals .grand td {
            border-top: 1.5pt solid #111111;
            padding-top: 4pt;
            font-size: 10.5pt;
            font-weight: bold;
        }
        .notes {
            margin-top: 8pt;
            padding: 5pt 6pt;
            border: 0.6pt dashed #999999;
            background-color: #fafafa;
            font-size: 8pt;
        }
        .footer {
            margin-top: 10pt;
            padding-top: 5pt;
            border-top: 0.6pt solid #dddddd;
            font-size: 7pt;
            color: #555555;
        }
    </style>
</head>
<body>
@php
    $isCod = $order->payment_method === 'cash';
    $buyerDigitalAddress = filled($order->digital_address) ? trim((string) $order->digital_address) : null;
    $buyerLocation = collect([$order->city, $order->region])->filter()->implode(', ') ?: null;
    $money = fn (float $n) => 'GHS '.number_format($n, 2);
    $storeAddress = $storeAddress ?? null;
    $storeDigitalAddress = $storeDigitalAddress ?? null;
    $storeLocation = $storeLocation ?? null;
@endphp

{{-- Header: brand | packing slip meta --}}
<table>
    <tr>
        <td class="top wrap" width="55%">
            <div class="brand">City<span>Shop</span></div>
            <div class="muted">cityunlock.net</div>
        </td>
        <td class="top right wrap" width="45%">
            <div class="doc-title">Packing slip</div>
            <div class="order-no">{{ $order->order_number }}</div>
            <div class="muted" style="margin-top:2pt;">
                Placed {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
            </div>
            <div class="muted">
                Printed {{ $printedAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}
            </div>
        </td>
    </tr>
</table>

<div class="accent">&nbsp;</div>

{{-- Ship from / Ship to (no nested tables — mPDF-safe) --}}
<table style="margin-bottom:8pt;">
    <tr>
        <td class="top" width="49%" style="padding-right:5pt;">
            <div class="box wrap">
                <div class="label">Ship from (seller)</div>
                <div class="field"><span class="k">Store name:</span> <strong>{{ $storeName }}</strong></div>
                <div class="field"><span class="k">Address:</span> {{ $storeAddress ?: '—' }}</div>
                <div class="field"><span class="k">Digital address:</span> {{ $storeDigitalAddress ?: '—' }}</div>
                <div class="field"><span class="k">Location:</span> {{ $storeLocation ?: '—' }}</div>
                <div class="field"><span class="k">Phone:</span> {{ filled($sellerPhone) ? $sellerPhone : '—' }}</div>
            </div>
        </td>
        <td class="top" width="51%" style="padding-left:5pt;">
            <div class="box wrap">
                <div class="label">Ship to (buyer)</div>
                <div class="field"><strong>{{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}</strong></div>
                @if($order->receiver_phone)
                    <div class="field"><span class="k">Phone:</span> {{ $order->receiver_phone }}</div>
                @endif
                <div class="field"><span class="k">Digital address:</span> {{ $buyerDigitalAddress ?: '—' }}</div>
                <div class="field"><span class="k">Location:</span> {{ $buyerLocation ?: '—' }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td class="wrap">
            <strong>{{ $paymentLabel }}</strong>
            &nbsp;
            <span class="pill">{{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}</span>
        </td>
        <td class="right muted">
            Lines: {{ $items->count() }} · Qty: {{ $items->sum('quantity') }}
        </td>
    </tr>
</table>

{{-- Items: Product (photo + name) | Qty | Unit | Line — fixed widths --}}
<table class="items" width="100%">
    <thead>
        <tr>
            <th width="8%" class="center">#</th>
            <th width="48%">Product</th>
            <th width="10%" class="center">Qty</th>
            <th width="17%" class="right">Unit</th>
            <th width="17%" class="right">Line</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $index => $item)
            @php $imageSrc = $itemImages[$item->id] ?? null; @endphp
            <tr>
                <td class="center mid" width="8%">{{ $index + 1 }}</td>
                <td class="wrap mid" width="48%">
                    @if($imageSrc)
                        <img class="thumb" src="{{ $imageSrc }}" width="36" height="36" alt="" style="float:left; margin-right:6pt; margin-bottom:2pt;">
                    @endif
                    <div class="pname">{{ $item->product_name }}</div>
                    @if($item->status)
                        <div class="pstatus">{{ str_replace('_', ' ', ucfirst($item->status->value ?? (string) $item->status)) }}</div>
                    @endif
                </td>
                <td class="center mid" width="10%">{{ $item->quantity }}</td>
                <td class="right mid" width="17%">{{ $money((float) $item->unit_price) }}</td>
                <td class="right mid" width="17%">{{ $money($item->lineTotal()) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table>
    <tr>
        <td></td>
        <td width="200">
            <table class="totals">
                <tr>
                    <td>Items subtotal</td>
                    <td class="amt">{{ $money($subtotal) }}</td>
                </tr>
                @if($shipping > 0)
                    <tr>
                        <td>Shipping</td>
                        <td class="amt">{{ $money($shipping) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td>All Total</td>
                    <td class="amt">{{ $money($allTotal) }}</td>
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

<table class="footer">
    <tr>
        <td class="top wrap" width="60%">For packing &amp; delivery only · Not a tax invoice</td>
        <td class="top right wrap" width="40%">{{ $storeName }} · cityunlock.net</td>
    </tr>
</table>
</body>
</html>
