<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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

    /** Open in browser — same PDF bytes as download. */
    public function stream(OrderItem $orderItem, User $seller): Response
    {
        return $this->respond($orderItem, $seller, download: false);
    }

    public function pdf(OrderItem $orderItem, User $seller): Response
    {
        return $this->respond($orderItem, $seller, download: true);
    }

    private function respond(OrderItem $orderItem, User $seller, bool $download): Response
    {
        $data = $this->payload($orderItem, $seller);
        $filename = $this->filename($data['order']);
        $binary = $this->renderPdfBinary($data);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function renderPdfBinary(array $data): string
    {
        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'tempDir' => $tempDir,
            'shrink_tables_to_fit' => 1,
        ]);

        $mpdf->SetTitle('Packing slip '.$data['order']->order_number);
        $mpdf->SetAuthor('CityShop');
        $mpdf->SetCreator('CityShop');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $html = view('seller.orders.packing-slip', $data)->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
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
     * mPDF gets a base64 data URI so file paths never break images.
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

        if (strlen($binary) > 1_500_000) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
