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
            line-height: 1.35;
        }
        table { border-collapse: collapse; width: 100%; }
        .muted { color: #555555; font-size: 7.5pt; }
        .brand { font-size: 16pt; font-weight: bold; line-height: 1.1; }
        .brand span { color: #ea580c; }
        .right { text-align: right; }
        .center { text-align: center; }
        .top { vertical-align: top; }
        .mid { vertical-align: middle; }
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
            margin-bottom: 6pt;
        }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #666666;
            letter-spacing: 0.3pt;
            margin-bottom: 3pt;
        }
        .field { margin-bottom: 2pt; font-size: 8.5pt; }
        .field .k { color: #666666; font-size: 7.5pt; }
        .items th {
            font-size: 7.5pt;
            color: #666666;
            text-align: left;
            padding: 4pt 3pt;
            border-bottom: 0.7pt solid #dddddd;
        }
        .items td {
            padding: 5pt 3pt;
            border-bottom: 0.4pt solid #eeeeee;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .thumb {
            width: 28pt;
            height: 28pt;
            object-fit: contain;
            border: 0.4pt solid #e5e5e5;
        }
        .totals {
            width: 45%;
            margin-left: auto;
            margin-top: 8pt;
        }
        .totals td { padding: 2pt 0; font-size: 8.5pt; }
        .totals .grand td {
            font-size: 11pt;
            font-weight: bold;
            padding-top: 5pt;
            border-top: 0.7pt solid #dddddd;
        }
        .totals .grand .amount { color: #ea580c; }
        .footer {
            margin-top: 14pt;
            text-align: center;
            color: #999999;
            font-size: 7.5pt;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="top" style="width: 55%;">
                <div class="brand">City<span>Shop</span></div>
                <div class="muted">cityunlock.net</div>
            </td>
            <td class="top right" style="width: 45%;">
                <div style="font-size: 11pt; font-weight: bold;">{{ $invoice->invoice_number }}</div>
                <div class="muted">{{ $typeLabel }}</div>
                <div class="muted">{{ $issuedLabel }}</div>
            </td>
        </tr>
    </table>

    <div class="accent"></div>

    @foreach ($sellerContacts as $index => $contact)
        <div class="box">
            <div class="label">{{ count($sellerContacts) > 1 ? 'Store '.($index + 1) : 'Seller' }}</div>
            <div class="field"><span class="k">Store name:</span> <strong>{{ $contact['store_name'] }}</strong></div>
            <div class="field"><span class="k">Address:</span> {{ $contact['address'] ?: '—' }}</div>
            @if (!empty($contact['location']))
                <div class="field"><span class="k">Location:</span> {{ $contact['location'] }}</div>
            @endif
            <div class="field"><span class="k">Phone:</span> {{ $contact['phone'] ?: '—' }}</div>
        </div>
    @endforeach

    <div style="margin-bottom: 8pt; font-size: 8pt;">
        @if ($invoice->checkout)
            <div><strong>Checkout:</strong> {{ $invoice->checkout->checkout_number }}</div>
        @endif
        @if ($invoice->order)
            <div><strong>Order:</strong> {{ $invoice->order->order_number }}</div>
        @endif
        <div>
            <strong>Payment:</strong>
            {{ ucfirst(str_replace('_', ' ', (string) $invoice->payment_status)) }}
            @if ($invoice->payment_method)
                · {{ str_replace('_', ' ', $invoice->payment_method) }}
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 52%;">Item</th>
                <th class="center" style="width: 12%;">Qty</th>
                <th class="right" style="width: 18%;">Unit</th>
                <th class="right" style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineItems as $line)
                <tr>
                    <td class="mid">
                        <table>
                            <tr>
                                <td class="mid" style="width: 34pt;">
                                    @if (!empty($line['pdf_image']))
                                        <img class="thumb" src="{{ $line['pdf_image'] }}" alt=""/>
                                    @endif
                                </td>
                                <td class="mid">
                                    <div style="font-weight: bold;">{{ $line['product_name'] ?? 'Item' }}</div>
                                    @if (!empty($line['seller']))
                                        <div class="muted">{{ $line['seller'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
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
        @if ((float) $invoice->shipping_cost > 0)
            <tr>
                <td>Shipping</td>
                <td class="right">GH₵{{ number_format((float) $invoice->shipping_cost, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="right amount">GH₵{{ number_format((float) $invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">Thank you for shopping on CityShop.</div>
</body>
</html>
