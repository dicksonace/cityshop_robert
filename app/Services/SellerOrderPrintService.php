<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
     *   itemImages: array<int, string|null>,
     *   subtotal: float,
     *   shipping: float,
     *   allTotal: float,
     *   printedAt: \Illuminate\Support\Carbon,
     *   paymentLabel: string,
     * }
     */
    public function payload(OrderItem $orderItem, User $seller, string $mode = 'html'): array
    {
        abort_unless($orderItem->seller_id === $seller->id, 403);

        $order = $orderItem->order()->with([
            'buyer',
            'items' => fn ($q) => $q->where('seller_id', $seller->id)->with(['product.images']),
        ])->firstOrFail();

        $seller->loadMissing('sellerProfile');

        $items = $order->items;
        $subtotal = (float) $items->sum(fn (OrderItem $item) => $item->lineTotal());
        $shipping = (float) $order->shipping_cost;
        $allTotal = round($subtotal + $shipping, 2);

        $itemImages = [];
        foreach ($items as $item) {
            $itemImages[$item->id] = $this->productImageSrc($item, $mode);
        }

        return [
            'order' => $order,
            'seller' => $seller,
            'storeName' => $seller->sellerProfile?->displayName() ?? $seller->name ?? 'Seller',
            'items' => $items,
            'itemImages' => $itemImages,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'allTotal' => $allTotal,
            'printedAt' => now(),
            'paymentLabel' => $this->paymentLabel($order),
        ];
    }

    public function html(OrderItem $orderItem, User $seller, bool $autoPrint = false): View
    {
        return view('seller.orders.packing-slip', [
            ...$this->payload($orderItem, $seller, 'html'),
            'focusItemId' => $orderItem->id,
            'mode' => 'html',
            'autoPrint' => $autoPrint,
        ]);
    }

    public function pdf(OrderItem $orderItem, User $seller): Response
    {
        $data = [
            ...$this->payload($orderItem, $seller, 'pdf'),
            'focusItemId' => $orderItem->id,
            'mode' => 'pdf',
            'autoPrint' => false,
        ];

        $filename = 'CityShop-Order-'.$data['order']->order_number.'.pdf';

        return Pdf::loadView('seller.orders.packing-slip', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
                'isFontSubsettingEnabled' => true,
            ])
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

    /**
     * Browser HTML uses a public /storage URL; DomPDF needs a local file path.
     */
    private function productImageSrc(OrderItem $item, string $mode): ?string
    {
        $images = $item->product?->images;
        if (! $images || $images->isEmpty()) {
            return null;
        }

        $path = $images->firstWhere('is_primary', true)?->path
            ?? $images->first()?->path;

        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');

        if ($mode === 'pdf') {
            $absolute = Storage::disk('public')->path($path);
            if (is_file($absolute)) {
                return $absolute;
            }

            $publicLinked = public_path('storage/'.$path);
            if (is_file($publicLinked)) {
                return $publicLinked;
            }

            return null;
        }

        return asset('storage/'.$path);
    }
}
