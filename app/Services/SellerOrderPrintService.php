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
     * @return array{
     *   order: Order,
     *   seller: User,
     *   storeName: string,
     *   storeAddress: string|null,
     *   storeDigitalAddress: string|null,
     *   storeLocation: string|null,
     *   sellerPhone: string|null,
     *   storeAddressLines: list<string>,
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

        $contact = $this->sellerContact($seller);

        return [
            'order' => $order,
            'seller' => $seller,
            'storeName' => $contact['storeName'],
            'storeAddress' => $contact['address'],
            'storeDigitalAddress' => $contact['digital_address'],
            'storeLocation' => $contact['location'],
            'sellerPhone' => $contact['phone'],
            'storeAddressLines' => array_values(array_filter([
                $contact['address'],
                $contact['digital_address'],
                $contact['location'],
            ])),
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
     * @return array{storeName: string, address: string|null, digital_address: string|null, location: string|null, phone: string|null}
     */
    private function sellerContact(User $seller): array
    {
        $profile = $seller->sellerProfile;

        $address = collect([
            $profile?->business_address,
            $seller->residential_address,
        ])->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->first(fn (string $v) => $v !== '') ?: null;

        $digitalAddress = is_string($seller->digital_address) ? trim($seller->digital_address) : '';
        $digitalAddress = $digitalAddress !== '' ? $digitalAddress : null;

        $location = collect([$seller->city, $seller->region])
            ->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->filter()
            ->unique()
            ->implode(', ');
        $location = $location !== '' ? $location : null;

        return [
            'storeName' => $profile?->displayName() ?? $seller->name ?? 'Seller',
            'address' => $address,
            'digital_address' => $digitalAddress,
            'location' => $location,
            'phone' => $this->sellerPhone($seller),
        ];
    }

    private function sellerPhone(User $seller): ?string
    {
        foreach ([$seller->mobile, $seller->whatsapp] as $phone) {
            $phone = is_string($phone) ? trim($phone) : '';
            if ($phone !== '') {
                return $phone;
            }
        }

        return null;
    }

    /**
     * Local filesystem path for mPDF (more reliable than data URIs).
     * WebP/GIF are converted to JPEG thumbs when GD is available.
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

        $prepared = $this->prepareImageForPdf($absolute, (int) $item->id);
        if ($prepared === null) {
            return null;
        }

        // mPDF expects forward slashes on Windows paths.
        return str_replace('\\', '/', $prepared);
    }

    private function prepareImageForPdf(string $absolute, int $itemId): ?string
    {
        $mime = @mime_content_type($absolute) ?: '';
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        $needsConvert = in_array($ext, ['webp', 'gif', 'bmp'], true)
            || in_array($mime, ['image/webp', 'image/gif', 'image/bmp'], true);

        $tempDir = storage_path('app/mpdf-temp/thumbs');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $thumbPath = $tempDir.DIRECTORY_SEPARATOR.'item-'.$itemId.'-'.md5($absolute.filemtime($absolute)).'.jpg';

        if (is_file($thumbPath) && filesize($thumbPath) > 0) {
            return $thumbPath;
        }

        if (! function_exists('imagecreatetruecolor')) {
            // No GD — only pass formats mPDF usually handles natively.
            if ($needsConvert) {
                return null;
            }

            return $absolute;
        }

        $source = match (true) {
            $mime === 'image/jpeg' || in_array($ext, ['jpg', 'jpeg'], true) => @imagecreatefromjpeg($absolute),
            $mime === 'image/png' || $ext === 'png' => @imagecreatefrompng($absolute),
            ($mime === 'image/webp' || $ext === 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($absolute),
            ($mime === 'image/gif' || $ext === 'gif') && function_exists('imagecreatefromgif') => @imagecreatefromgif($absolute),
            default => false,
        };

        if ($source === false) {
            return $needsConvert ? null : $absolute;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);

            return null;
        }

        $max = 160;
        $scale = min($max / $srcW, $max / $srcH, 1);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $thumb = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        $ok = imagejpeg($thumb, $thumbPath, 82);
        imagedestroy($thumb);

        return $ok && is_file($thumbPath) ? $thumbPath : null;
    }
}
