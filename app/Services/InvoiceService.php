<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Checkout;
use App\Models\Invoice;
use App\Models\Order;
use App\Notifications\InvoiceSentNotification;
use Illuminate\Support\Collection;

class InvoiceService
{
    public function generateForCheckout(Checkout $checkout): Collection
    {
        if ($checkout->invoices()->exists()) {
            return $checkout->invoices()->get();
        }

        $checkout->load(['orders.items', 'orders.seller.sellerProfile', 'buyer']);

        $invoices = collect();

        $masterLines = [];
        $masterSubtotal = 0;
        $masterCommission = 0;

        foreach ($checkout->orders as $order) {
            $sellerName = $order->seller?->sellerProfile?->displayName() ?? $order->seller?->name ?? 'Seller';
            foreach ($order->items as $item) {
                $lineTotal = $item->lineTotal();
                $masterLines[] = [
                    'seller' => $sellerName,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => $lineTotal,
                ];
                $masterSubtotal += $lineTotal;
            }
            $masterCommission += (float) $order->commission_amount;

            $invoices->push($this->createInvoice(
                $checkout,
                $order,
                $checkout->buyer_id,
                InvoiceType::Customer,
                $this->orderLineItems($order),
                (float) $order->subtotal,
                (float) $order->commission_amount,
                (float) $order->shipping_cost,
                (float) $order->total,
                $order->payment_method,
                $order->payment_status->value,
            ));

            if ($order->seller_id) {
                $invoices->push($this->createInvoice(
                    $checkout,
                    $order,
                    $order->seller_id,
                    InvoiceType::Seller,
                    $this->orderLineItems($order, forSeller: true),
                    (float) $order->subtotal,
                    (float) $order->commission_amount,
                    (float) $order->shipping_cost,
                    (float) $order->subtotal - (float) $order->commission_amount,
                    $order->payment_method,
                    $order->payment_status->value,
                ));
            }
        }

        $master = $this->createInvoice(
            $checkout,
            null,
            $checkout->buyer_id,
            InvoiceType::CustomerMaster,
            $masterLines,
            $masterSubtotal,
            $masterCommission,
            (float) $checkout->shipping_cost,
            (float) $checkout->total,
            null,
            $checkout->payment_status->value,
        );

        $invoices->prepend($master);

        return $invoices;
    }

    public function sendInvoices(Checkout $checkout): void
    {
        $invoices = $this->generateForCheckout($checkout);

        foreach ($invoices as $invoice) {
            $invoice->user->notify(new InvoiceSentNotification($invoice));
            $invoice->update(['sent_at' => now()]);
        }
    }

    private function createInvoice(
        Checkout $checkout,
        ?Order $order,
        int $userId,
        InvoiceType $type,
        array $lineItems,
        float $subtotal,
        float $commission,
        float $shipping,
        float $total,
        ?string $paymentMethod,
        string $paymentStatus,
    ): Invoice {
        return Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'checkout_id' => $checkout->id,
            'order_id' => $order?->id,
            'user_id' => $userId,
            'type' => $type,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'commission_amount' => $commission,
            'shipping_cost' => $shipping,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'issued_at' => now(),
        ]);
    }

    private function orderLineItems(Order $order, bool $forSeller = false): array
    {
        return $order->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => $item->lineTotal(),
            'seller_amount' => $forSeller ? (float) $item->seller_amount : null,
            'commission' => $forSeller ? (float) $item->commission_amount : null,
        ])->all();
    }
}
