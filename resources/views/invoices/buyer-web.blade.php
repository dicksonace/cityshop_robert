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
            background: #f4f4f5;
            color: #111;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.45;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 8px;
            padding: 10px 12px;
            padding-top: calc(10px + env(safe-area-inset-top, 0px));
            background: #fff;
            border-bottom: 1px solid #e5e5e5;
        }
        .toolbar a, .toolbar button {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 0;
            cursor: pointer;
        }
        .toolbar .primary { background: #ea580c; color: #fff; }
        .toolbar .secondary { background: #fff; color: #111; border: 1px solid #d4d4d4; }
        .sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            padding: 16px 16px 32px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            padding-bottom: 12px;
            border-bottom: 3px solid #ea580c;
        }
        .brand { font-size: 22px; font-weight: 800; line-height: 1.1; }
        .brand span { color: #ea580c; }
        .muted { color: #737373; font-size: 12px; }
        .invoice-no { font-size: 16px; font-weight: 700; text-align: right; }
        .grid {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        @media (min-width: 640px) {
            .sheet { padding: 28px 32px 40px; margin: 16px auto 40px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
            .grid { grid-template-columns: 1fr 1fr; }
        }
        .box {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            padding: 12px;
        }
        .label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #737373;
            margin-bottom: 6px;
        }
        .field { margin: 0 0 4px; font-size: 13px; }
        .field .k { color: #737373; }
        .meta { margin: 12px 0; font-size: 13px; color: #404040; }
        .item {
            display: flex;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .thumb {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            background: #fff;
            flex-shrink: 0;
        }
        .thumb-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a3a3a3;
            font-size: 12px;
        }
        .item-body { min-width: 0; flex: 1; }
        .item-name { font-weight: 650; }
        .item-meta { color: #737373; font-size: 12px; margin-top: 2px; }
        .item-total { font-weight: 700; white-space: nowrap; padding-top: 2px; }
        .totals { margin-top: 8px; max-width: 280px; margin-left: auto; }
        .totals .row { display: flex; justify-content: space-between; gap: 16px; padding: 4px 0; font-size: 14px; color: #525252; }
        .totals .grand { font-size: 18px; font-weight: 800; color: #111; border-top: 1px solid #e5e5e5; margin-top: 6px; padding-top: 8px; }
        .totals .grand .amount { color: #ea580c; }
        .footer { margin-top: 20px; text-align: center; color: #a3a3a3; font-size: 12px; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; padding: 0; max-width: none; box-shadow: none; border-radius: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a class="secondary" href="{{ route('invoices.pdf', $invoice) }}">Save PDF</a>
    </div>

    <article class="sheet">
        <div class="header">
            <div>
                <div class="brand">City<span>Shop</span></div>
                <div class="muted">cityunlock.net</div>
            </div>
            <div>
                <div class="invoice-no">{{ $invoice->invoice_number }}</div>
                <div class="muted">{{ $typeLabel }}</div>
                <div class="muted">{{ $issuedLabel }}</div>
            </div>
        </div>

        <div class="grid">
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
        </div>

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

        @foreach ($lineItems as $line)
            @php
                $src = $line['image'] ?? null;
                if (is_string($src) && $src !== '' && ! str_starts_with($src, 'http')) {
                    $src = asset(ltrim($src, '/'));
                }
            @endphp
            <div class="item">
                @if ($src)
                    <img class="thumb" src="{{ $src }}" alt="">
                @else
                    <div class="thumb thumb-empty">—</div>
                @endif
                <div class="item-body">
                    <div class="item-name">{{ $line['product_name'] ?? 'Item' }}</div>
                    @if (!empty($line['seller']))
                        <div class="item-meta">{{ $line['seller'] }}</div>
                    @endif
                    <div class="item-meta">
                        Qty {{ $line['quantity'] ?? 1 }}
                        @if (isset($line['unit_price']))
                            · GH₵{{ number_format((float) $line['unit_price'], 2) }}
                        @endif
                    </div>
                </div>
                <div class="item-total">
                    @if (isset($line['total']))
                        GH₵{{ number_format((float) $line['total'], 2) }}
                    @else
                        —
                    @endif
                </div>
            </div>
        @endforeach

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

        <div class="footer">Thank you for shopping on CityShop.</div>
    </article>
</body>
</html>
