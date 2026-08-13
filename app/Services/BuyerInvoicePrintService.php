<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class BuyerInvoicePrintService
{
    /**
     * @return array{
     *   invoice: Invoice,
     *   typeLabel: string,
     *   sellerContacts: list<array{store_name: string, address: string|null, digital_address: string|null, location: string|null, phone: string|null}>,
     *   buyerShipTo: array{name: string, phone: string|null, digital_address: string|null, location: string|null, delivery_notes: string|null},
     *   deliveryFees: float,
     *   shippingFees: float,
     *   lineItems: list<array<string, mixed>>,
     *   issuedLabel: string,
     * }
     */
    public function payload(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'checkout.orders.items.product.images',
            'checkout.orders.seller.sellerProfile',
            'checkout.buyer',
            'order.items.product.images',
            'order.seller.sellerProfile',
            'order.seller',
            'order.buyer',
        ]);

        $lineItems = $this->lineItemsWithImages($invoice, forPdf: true);
        $fees = $this->resolveFees($invoice);

        return [
            'invoice' => $invoice,
            'typeLabel' => match ($invoice->type->value) {
                'customer_master' => 'Master invoice',
                'customer' => 'Seller invoice',
                default => $invoice->type->value,
            },
            'sellerContacts' => $this->resolveSellerContacts($invoice),
            'buyerShipTo' => $this->resolveBuyerShipTo($invoice),
            'deliveryFees' => $fees['delivery'],
            'shippingFees' => $fees['shipping'],
            'lineItems' => $lineItems,
            'issuedLabel' => optional($invoice->issued_at)->format('j M Y') ?? now()->format('j M Y'),
        ];
    }

    public function stream(Invoice $invoice): Response
    {
        return $this->respond($invoice, download: false);
    }

    public function pdf(Invoice $invoice): Response
    {
        return $this->respond($invoice, download: true);
    }

    /**
     * @return list<array{store_name: string, address: string|null, digital_address: string|null, location: string|null, phone: string|null}>
     */
    public function resolveSellerContacts(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'checkout.orders.seller.sellerProfile',
            'order.seller.sellerProfile',
            'order.seller',
        ]);

        $sellers = collect();

        if ($invoice->order?->seller) {
            $sellers->push($invoice->order->seller);
        } elseif ($invoice->checkout) {
            $sellers = $invoice->checkout->orders
                ->map(fn ($order) => $order->seller)
                ->filter();
        }

        return $sellers
            ->unique('id')
            ->values()
            ->map(fn (User $seller) => $this->sellerContactFromUser($seller))
            ->all();
    }

    /**
     * @return array{name: string, phone: string|null, digital_address: string|null, location: string|null, delivery_notes: string|null}
     */
    public function resolveBuyerShipTo(Invoice $invoice): array
    {
        $invoice->loadMissing(['checkout.buyer', 'order.buyer']);

        $source = $invoice->order ?? $invoice->checkout;
        $buyer = $invoice->order?->buyer ?? $invoice->checkout?->buyer;

        $name = collect([
            $source?->receiver_name,
            $buyer?->name,
        ])->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->first(fn (string $v) => $v !== '') ?: 'Buyer';

        $phone = collect([
            $source?->receiver_phone,
            $buyer?->mobile,
        ])->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->first(fn (string $v) => $v !== '') ?: null;

        $digital = is_string($source?->digital_address) ? trim($source->digital_address) : '';
        $digital = $digital !== '' ? $digital : null;

        $location = collect([$source?->city, $source?->region])
            ->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->filter()
            ->unique()
            ->implode(', ') ?: null;

        $notes = is_string($source?->delivery_notes) ? trim($source->delivery_notes) : '';
        $notes = $notes !== '' ? $notes : null;

        return [
            'name' => $name,
            'phone' => $phone,
            'digital_address' => $digital,
            'location' => $location,
            'delivery_notes' => $notes,
        ];
    }

    /**
     * Product delivery fees are stored as invoice shipping_cost.
     *
     * @return array{delivery: float, shipping: float}
     */
    public function resolveFees(Invoice $invoice): array
    {
        $amount = round((float) $invoice->shipping_cost, 2);

        return [
            'delivery' => $amount,
            // Same charge today — shown under both labels Robert requested on print.
            'shipping' => $amount,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineItemsWithImages(Invoice $invoice, bool $forPdf = false): array
    {
        /** @var Collection<int, OrderItem> $orderItems */
        $orderItems = collect();

        if ($invoice->order) {
            $orderItems = $invoice->order->items;
        } elseif ($invoice->checkout) {
            $orderItems = $invoice->checkout->orders->flatMap(fn ($order) => $order->items);
        }

        $pool = $orderItems->values();

        return collect($invoice->line_items ?? [])->map(function (array $line) use (&$pool, $forPdf) {
            $name = $line['product_name'] ?? null;
            $matchIndex = $pool->search(fn (OrderItem $item) => $item->product_name === $name);

            if ($matchIndex !== false) {
                /** @var OrderItem $match */
                $match = $pool->get($matchIndex);
                $pool = $pool->forget($matchIndex)->values();

                $path = $match->product?->images?->firstWhere('is_primary', true)?->path
                    ?? $match->product?->images?->first()?->path;

                $line['image'] = $path ? '/storage/'.ltrim(str_replace('\\', '/', $path), '/') : null;
                $line['product_id'] = $match->product_id;
                $line['status'] = $match->status?->value ?? (is_string($match->status) ? $match->status : null);

                if ($forPdf) {
                    $line['pdf_image'] = $this->productImageSrc($match);
                }
            } else {
                $line['image'] = $line['image'] ?? null;
            }

            return $line;
        })->values()->all();
    }

    private function respond(Invoice $invoice, bool $download): Response
    {
        $data = $this->payload($invoice);
        $filename = 'CityShop-Invoice-'.$invoice->invoice_number.'.pdf';
        $binary = $this->renderPdfBinary($data);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
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
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'tempDir' => $tempDir,
            // Keep column widths — shrinking tables was scrambling Qty/Unit/Total.
            'shrink_tables_to_fit' => 0,
        ]);

        $mpdf->SetTitle('Invoice '.$data['invoice']->invoice_number);
        $mpdf->SetAuthor('CityShop');
        $mpdf->SetCreator('CityShop');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $html = view('invoices.buyer', $data)->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return array{store_name: string, address: string|null, digital_address: string|null, location: string|null, phone: string|null}
     */
    private function sellerContactFromUser(User $seller): array
    {
        $profile = $seller->sellerProfile;

        $storeName = collect([
            $profile?->store_name,
            $profile?->business_name,
            $seller->name,
        ])->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->first(fn (string $v) => $v !== '') ?: 'Seller';

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
            ->implode(', ') ?: null;

        $phone = collect([$seller->mobile, $seller->whatsapp])
            ->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->first(fn (string $v) => $v !== '') ?: null;

        return [
            'store_name' => $storeName,
            'address' => $address,
            'digital_address' => $digitalAddress,
            'location' => $location,
            'phone' => $phone,
        ];
    }

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

        return $prepared ? str_replace('\\', '/', $prepared) : null;
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

        $thumbPath = $tempDir.DIRECTORY_SEPARATOR.'invoice-item-'.$itemId.'-'.md5($absolute.filemtime($absolute)).'.jpg';

        if (is_file($thumbPath) && filesize($thumbPath) > 0) {
            return $thumbPath;
        }

        if (! function_exists('imagecreatetruecolor')) {
            return $needsConvert ? null : $absolute;
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

        $max = 120;
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
