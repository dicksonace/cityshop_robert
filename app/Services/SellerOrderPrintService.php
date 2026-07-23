<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SellerOrderPrintService
{
    /**
     * Build a packing-slip payload for one seller’s items on an order.
     *
     * @return array{
     *   order: Order,
     *   seller: User,
     *   storeName: string,
     *   storeAddress: string|null,
     *   items: Collection<int, OrderItem>,
     *   itemImages: array<int, string|null>,
     *   subtotal: float,
     *   shipping: float,
     *   allTotal: float,
     *   printedAt: \Illuminate\Support\Carbon,
     *   paymentLabel: string,
     *   focusItemId: int,
     * }
     */
    public function payload(OrderItem $orderItem, User $seller): array
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
            $itemImages[$item->id] = $this->productImageSrc($item);
        }

        $profile = $seller->sellerProfile;

        return [
            'order' => $order,
            'seller' => $seller,
            'storeName' => $profile?->displayName() ?? $seller->name ?? 'Seller',
            'storeAddress' => filled($profile?->business_address) ? trim((string) $profile->business_address) : null,
            'items' => $items,
            'itemImages' => $itemImages,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'allTotal' => $allTotal,
            'printedAt' => now(),
            'paymentLabel' => $this->paymentLabel($order),
            'focusItemId' => $orderItem->id,
        ];
    }

    /** Open in browser — same PDF bytes as download (no HTML size mismatch). */
    public function stream(OrderItem $orderItem, User $seller): Response
    {
        $data = $this->payload($orderItem, $seller);
        $filename = $this->filename($data['order']);

        return $this->makePdf($data)->stream($filename);
    }

    public function pdf(OrderItem $orderItem, User $seller): Response
    {
        $data = $this->payload($orderItem, $seller);
        $filename = $this->filename($data['order']);

        return $this->makePdf($data)->download($filename);
    }

    private function makePdf(array $data): \Barryvdh\DomPDF\PDF
    {
        // dpi 72 => CSS pt maps 1:1 to PDF points (stable page size).
        return Pdf::loadView('seller.orders.packing-slip', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('dpi', 72)
            ->setOption('isFontSubsettingEnabled', true);
    }

    private function filename(Order $order): string
    {
        return 'CityShop-Order-'.$order->order_number.'.pdf';
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
     * DomPDF gets a base64 data URI so Windows paths and missing chroot never break images.
     */
    private function productImageSrc(OrderItem $item): ?string
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

        $path = ltrim(str_replace('\\', '/', $path), '/');

        $absolute = Storage::disk('public')->path($path);
        if (! is_file($absolute)) {
            $absolute = public_path('storage/'.$path);
        }

        if (! is_file($absolute)) {
            return null;
        }

        $mime = @mime_content_type($absolute) ?: 'image/jpeg';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $binary = @file_get_contents($absolute);
        if ($binary === false || $binary === '') {
            return null;
        }

        // Keep PDF small — skip huge originals.
        if (strlen($binary) > 1_500_000) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
