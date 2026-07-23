<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SellerOrderPrintService
{
    /**
     * Build a packing-slip payload for one seller’s items on an order.
     *
     * @return array{
     *   order: Order,
     *   seller: User,
     *   storeName: string,
     *   items: Collection<int, OrderItem>,
     *   subtotal: float,
     *   printedAt: \Illuminate\Support\Carbon,
     *   paymentLabel: string,
     * }
     */
    public function payload(OrderItem $orderItem, User $seller): array
    {
        abort_unless($orderItem->seller_id === $seller->id, 403);

        $order = $orderItem->order()->with([
            'buyer',
            'items' => fn ($q) => $q->where('seller_id', $seller->id),
        ])->firstOrFail();

        $seller->loadMissing('sellerProfile');

        $items = $order->items;
        $subtotal = (float) $items->sum(fn (OrderItem $item) => $item->lineTotal());

        return [
            'order' => $order,
            'seller' => $seller,
            'storeName' => $seller->sellerProfile?->displayName() ?? $seller->name ?? 'Seller',
            'items' => $items,
            'subtotal' => $subtotal,
            'printedAt' => now(),
            'paymentLabel' => $this->paymentLabel($order),
        ];
    }

    public function html(OrderItem $orderItem, User $seller, bool $autoPrint = false): View
    {
        return view('seller.orders.packing-slip', [
            ...$this->payload($orderItem, $seller),
            'focusItemId' => $orderItem->id,
            'mode' => 'html',
            'autoPrint' => $autoPrint,
        ]);
    }

    public function pdf(OrderItem $orderItem, User $seller): Response
    {
        $data = [
            ...$this->payload($orderItem, $seller),
            'focusItemId' => $orderItem->id,
            'mode' => 'pdf',
            'autoPrint' => false,
        ];

        $filename = 'CityShop-Order-'.$data['order']->order_number.'.pdf';

        return Pdf::loadView('seller.orders.packing-slip', $data)
            ->setPaper('a4')
            ->download($filename);
    }

    private function paymentLabel(Order $order): string
    {
        if ($order->payment_method === 'cash') {
            return 'Cash on delivery';
        }

        if ($order->payment_channel?->value === 'direct') {
            return $order->payment_status->value === 'paid'
                ? 'Paid to seller (direct)'
                : 'Pay to seller (pending)';
        }

        return $order->payment_status->value === 'paid'
            ? 'Paid · CityShop secured'
            : 'CityShop secured (awaiting payment)';
    }
}
