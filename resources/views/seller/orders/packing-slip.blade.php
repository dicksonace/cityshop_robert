<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing slip — {{ $order->order_number }}</title>
    <style>
        /*
         * DomPDF-only layout (print + download both use this PDF).
         * dpi=72 so 1pt CSS = 1pt PDF. Full content width. No floats/flex.
         */
        @page {
            margin: 10mm 10mm 10mm 10mm;
        }
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.25;
            color: #111111;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .wrap {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
        .right { text-align: right; }
        .muted { color: #555555; font-size: 7.5pt; }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
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
            letter-spacing: 0.6pt;
            text-transform: uppercase;
        }
        .order-no {
            font-size: 9.5pt;
            font-weight: bold;
            margin-top: 1pt;
            word-break: break-all;
        }
        .rule {
            border: 0;
            border-top: 1.5pt solid #111111;
            margin: 6pt 0 7pt 0;
        }
        .accent {
            border: 0;
            border-top: 2.5pt solid #ea580c;
            margin: 0 0 7pt 0;
        }

        .box {
            border: 0.6pt solid #cccccc;
            padding: 5pt 6pt;
            background: #fafafa;
        }
        .box strong { font-size: 9pt; }

        .meta {
            margin: 0 0 7pt 0;
            font-size: 8pt;
        }
        .meta td { padding: 0; vertical-align: middle; }
        .pill {
            display: inline-block;
            padding: 1pt 5pt;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            background: #ecfdf5;
            color: #047857;
        }
        .pill.cod { background: #ccfbf1; color: #0f766e; }
        .pill.pending { background: #fff7ed; color: #c2410c; }

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
            padding: 4pt 2pt;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .items .c-num { width: 18pt; }
        .items .c-qty { width: 32pt; text-align: right; }
        .items .c-money { width: 72pt; text-align: right; }
        .thumb {
            width: 28pt;
            height: 28pt;
            border: 0.5pt solid #dddddd;
        }
        .thumb-ph {
            width: 28pt;
            height: 28pt;
            border: 0.5pt solid #dddddd;
            background: #f3f4f6;
            text-align: center;
            font-size: 7pt;
            color: #999999;
            line-height: 28pt;
        }
        .pname { font-weight: bold; font-size: 8.5pt; }
        .pstatus { color: #666666; font-size: 7pt; margin-top: 1pt; }

        .totals {
            width: 200pt;
            margin-top: 4pt;
        }
        .totals td {
            padding: 1.5pt 0;
            font-size: 8.5pt;
        }
        .totals td.amt {
            text-align: right;
            padding-left: 10pt;
            white-space: nowrap;
            width: 90pt;
        }
        .totals .grand td {
            border-top: 1.5pt solid #111111;
            padding-top: 4pt;
            font-size: 10pt;
            font-weight: bold;
        }

        .notes {
            margin-top: 8pt;
            padding: 5pt 6pt;
            border: 0.6pt dashed #999999;
            background: #fafafa;
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
@endphp

<table>
    <tr>
        <td class="top wrap" style="width:52%;">
            <div class="brand">City<span>Shop</span></div>
            <div class="muted" style="margin-top:1pt;">cityunlock.net</div>
            <div class="wrap" style="margin-top:3pt; font-size:8.5pt;"><strong>{{ $storeName }}</strong></div>
            @if(filled($storeAddress))
                <div class="muted wrap">{{ $storeAddress }}</div>
            @endif
        </td>
        <td class="top right wrap" style="width:48%;">
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

<hr class="accent">

<table style="margin-bottom:7pt;">
    <tr>
        <td class="top" style="width:49%; padding-right:1%;">
            <div class="box wrap">
                <div class="label">Ship from</div>
                <strong>{{ $storeName }}</strong><br>
                @if(filled($storeAddress))
                    {{ $storeAddress }}
                @endif
            </div>
        </td>
        <td class="top" style="width:49%; padding-left:1%;">
            <div class="box wrap">
                <div class="label">Ship to</div>
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
            <span class="pill {{ $isCod ? 'cod' : ($isPendingPay ? 'pending' : '') }}">
                {{ $isCod ? 'COD' : strtoupper($order->payment_status->value) }}
            </span>
        </td>
        <td class="right muted" style="white-space:nowrap;">
            Lines: {{ $items->count() }}
            &nbsp;·&nbsp;
            Qty: {{ $items->sum('quantity') }}
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="c-num">#</th>
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
                <td class="wrap">
                    <table>
                        <tr>
                            <td class="mid" style="width:34pt; padding-right:5pt;">
                                @if($imageSrc)
                                    <img class="thumb" src="{{ $imageSrc }}" width="28" height="28" alt="">
                                @else
                                    <div class="thumb-ph">—</div>
                                @endif
                            </td>
                            <td class="mid wrap">
                                <div class="pname">{{ $item->product_name }}</div>
                                @if($item->status)
                                    <div class="pstatus">{{ str_replace('_', ' ', ucfirst($item->status->value ?? (string) $item->status)) }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
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
        <td style="width:200pt;">
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
        <td class="top wrap" style="width:60%;">
            For packing &amp; delivery only · Not a tax invoice
        </td>
        <td class="top right wrap" style="width:40%;">
            {{ $storeName }} · cityunlock.net
        </td>
    </tr>
</table>
</body>
</html>
