<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice — {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 9pt;
            color: #111111;
            line-height: 1.4;
        }
        table { border-collapse: collapse; }
        .w-full { width: 100%; }
        .fixed { table-layout: fixed; }
        .muted { color: #666666; font-size: 7.5pt; }
        .brand { font-size: 18pt; font-weight: bold; line-height: 1.1; }
        .brand span { color: #ea580c; }
        .right { text-align: right; }
        .center { text-align: center; }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
        .accent {
            height: 3pt;
            background: #ea580c;
            border: 0;
            margin: 8pt 0 10pt 0;
        }
        .box {
            border: 0.6pt solid #d4d4d4;
            padding: 7pt 8pt;
            background-color: #fafafa;
        }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #737373;
            letter-spacing: 0.4pt;
            margin-bottom: 4pt;
        }
        .field { margin: 0 0 2.5pt 0; font-size: 8.5pt; }
        .field .k { color: #737373; font-size: 7.5pt; }
        .meta {
            margin: 0 0 10pt 0;
            font-size: 8pt;
            color: #404040;
        }
        .meta strong { color: #111111; }
        .items th {
            font-size: 7.5pt;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            padding: 5pt 4pt;
            border-bottom: 0.8pt solid #d4d4d4;
            background: #f5f5f5;
        }
        .items td {
            padding: 7pt 4pt;
            border-bottom: 0.5pt solid #e5e5e5;
            font-size: 8.5pt;
            vertical-align: middle;
            overflow: hidden;
        }
        .thumb {
            width: 26pt;
            height: 26pt;
            object-fit: contain;
            border: 0.4pt solid #e5e5e5;
        }
        .item-name { font-weight: bold; font-size: 8.5pt; }
        .totals {
            width: 48%;
            margin-left: auto;
            margin-top: 10pt;
        }
        .totals td { padding: 3pt 0; font-size: 8.5pt; }
        .totals .grand td {
            font-size: 11pt;
            font-weight: bold;
            padding-top: 6pt;
            border-top: 0.8pt solid #d4d4d4;
        }
        .totals .grand .amount { color: #ea580c; }
        .footer {
            margin-top: 16pt;
            text-align: center;
            color: #a3a3a3;
            font-size: 7.5pt;
        }
        .spacer { height: 8pt; }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="w-full">
        <tr>
            <td class="top" style="width: 58%;">
                <div class="brand">City<span>Shop</span></div>
                <div class="muted">cityunlock.net</div>
            </td>
            <td class="top right" style="width: 42%;">
                <div style="font-size: 11pt; font-weight: bold;">{{ $invoice->invoice_number }}</div>
                <div class="muted">{{ $typeLabel }}</div>
                <div class="muted">{{ $issuedLabel }}</div>
            </td>
        </tr>
    </table>

    <div class="accent"></div>

    {{-- Seller + Ship-to side by side --}}
    <table class="w-full fixed">
        <tr>
            <td class="top" style="width: 49%; padding-right: 4pt;">
                @forelse ($sellerContacts as $index => $contact)
                    <div class="box" @if ($index > 0) style="margin-top: 6pt;" @endif>
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
                        <div class="field muted">—</div>
                    </div>
                @endforelse
            </td>
            <td class="top" style="width: 2%;"></td>
            <td class="top" style="width: 49%; padding-left: 4pt;">
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
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    <div class="meta">
        @if ($invoice->checkout)
            <strong>Checkout:</strong> {{ $invoice->checkout->checkout_number }}
            &nbsp;&nbsp;
        @endif
        @if ($invoice->order)
            <strong>Order:</strong> {{ $invoice->order->order_number }}
            &nbsp;&nbsp;
        @endif
        <strong>Payment:</strong>
        {{ ucfirst(str_replace('_', ' ', (string) $invoice->payment_status)) }}
        @if ($invoice->payment_method)
            · {{ str_replace('_', ' ', $invoice->payment_method) }}
        @endif
    </div>

    {{-- Line items: fixed columns, no nested tables --}}
    <table class="items w-full fixed">
        <thead>
            <tr>
                <th style="width: 10%;" class="center">Img</th>
                <th style="width: 42%;" class="left">Item</th>
                <th style="width: 10%;" class="center">Qty</th>
                <th style="width: 19%;" class="right">Unit</th>
                <th style="width: 19%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineItems as $line)
                <tr>
                    <td class="center mid">
                        @if (!empty($line['pdf_image']))
                            <img class="thumb" src="{{ $line['pdf_image'] }}" alt=""/>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="mid">
                        <div class="item-name">{{ $line['product_name'] ?? 'Item' }}</div>
                        @if (!empty($line['seller']))
                            <div class="muted">{{ $line['seller'] }}</div>
                        @endif
                    </td>
                    <td class="center mid">{{ $line['quantity'] ?? 1 }}</td>
                    <td class="right mid">
                        @if (isset($line['unit_price']))
                            GH₵{{ number_format((float) $line['unit_price'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="right mid" style="font-weight: bold;">
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

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="right">GH₵{{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>Delivery fees</td>
            <td class="right">GH₵{{ number_format((float) $deliveryFees, 2) }}</td>
        </tr>
        <tr>
            <td>Shipping fees</td>
            <td class="right">
                @if ((float) $shippingFees > 0 && abs((float) $shippingFees - (float) $deliveryFees) < 0.001)
                    Same as delivery
                @else
                    GH₵{{ number_format((float) $shippingFees, 2) }}
                @endif
            </td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="right amount">GH₵{{ number_format((float) $invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">Thank you for shopping on CityShop.</div>
</body>
</html>
