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
            line-height: 1.3;
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
        .muted { color: #555555; font-size: 7.5pt; }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666666;
            margin-bottom: 2pt;
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
        .meta {
            margin-bottom: 7pt;
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
            text-align: left;
            border-bottom: 1pt solid #111111;
            padding: 3pt 2pt;
        }
        .items td {
            border-bottom: 0.5pt solid #e5e5e5;
            padding: 5pt 2pt;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .c-num { width: 16pt; }
        .c-img { width: 42pt; }
        .c-qty { width: 32pt; text-align: right; }
        .c-money { width: 72pt; text-align: right; }
        .thumb {
            width: 38pt;
            height: 38pt;
            border: 0.5pt solid #dddddd;
        }
        .thumb-ph {
            width: 38pt;
            height: 38pt;
            border: 0.5pt solid #dddddd;
            background-color: #f3f4f6;
            text-align: center;
            color: #999999;
            font-size: 7pt;
            line-height: 38pt;
        }
        .pname { font-weight: bold; font-size: 8.5pt; }
        .pstatus { color: #666666; font-size: 7pt; }
        .totals {
            width: 210pt;
            margin-top: 4pt;
        }
        .totals td {
            padding: 2pt 0;
            font-size: 8.5pt;
        }
        .totals .amt {
            text-align: right;
            width: 95pt;
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
    $isPendingPay = $order->payment_status->value !== 'paid' && ! $isCod;
    $shipToLines = array_values(array_filter([
        $order->digital_address,
        collect([$order->city, $order->region])->filter()->implode(', '),
    ]));
    $money = fn (float $n) => 'GHS '.number_format($n, 2);
    $storeAddressLines = $storeAddressLines ?? [];
@endphp

<table>
    <tr>
        <td class="top wrap" width="52%">
            <div class="brand">City<span>Shop</span></div>
            <div class="muted">cityunlock.net</div>
            <div class="wrap" style="margin-top:3pt; font-size:8.5pt;"><strong>{{ $storeName }}</strong></div>
            @foreach($storeAddressLines as $line)
                <div class="muted wrap">{{ $line }}</div>
            @endforeach
        </td>
        <td class="top right wrap" width="48%">
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

<table style="margin-bottom:7pt;">
    <tr>
        <td class="top" width="49%" style="padding-right:4pt;">
            <div class="box wrap">
                <div class="label">Ship from (seller)</div>
                <strong>{{ $storeName }}</strong><br>
                @forelse($storeAddressLines as $line)
                    {{ $line }}<br>
                @empty
                    <span class="muted">Seller address not set on profile</span><br>
                @endforelse
                @if(filled($sellerPhone))
                    Tel: {{ $sellerPhone }}
                @endif
            </div>
        </td>
        <td class="top" width="49%" style="padding-left:4pt;">
            <div class="box wrap">
                <div class="label">Ship to (buyer)</div>
                <strong>{{ $order->receiver_name ?: ($order->buyer?->name ?? 'Buyer') }}</strong><br>
                @if($order->receiver_phone)
                    Tel: {{ $order->receiver_phone }}<br>
                @endif
                @foreach($shipToLines as $line)
                    {{ $line }}<br>
                @endforeach
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

<table class="items">
    <thead>
        <tr>
            <th class="c-num">#</th>
            <th class="c-img">Photo</th>
            <th>Product</th>
            <th class="c-qty">Qty</th>
            <th class="c-money">Unit</th>
            <th class="c-money">Line</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $index => $item)
            @php $imageSrc = $itemImages[$item->id] ?? null; @endphp
            <tr>
                <td class="c-num">{{ $index + 1 }}</td>
                <td class="c-img mid">
                    @if($imageSrc)
                        <img class="thumb" src="{{ $imageSrc }}" width="38" height="38" alt="">
                    @else
                        <div class="thumb-ph">—</div>
                    @endif
                </td>
                <td class="wrap mid">
                    <div class="pname">{{ $item->product_name }}</div>
                    @if($item->status)
                        <div class="pstatus">{{ str_replace('_', ' ', ucfirst($item->status->value ?? (string) $item->status)) }}</div>
                    @endif
                </td>
                <td class="c-qty">{{ $item->quantity }}</td>
                <td class="c-money">{{ $money((float) $item->unit_price) }}</td>
                <td class="c-money">{{ $money($item->lineTotal()) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table>
    <tr>
        <td></td>
        <td width="210">
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
