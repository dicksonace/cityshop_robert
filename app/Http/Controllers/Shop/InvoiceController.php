<?php

namespace App\Http\Controllers\Shop;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->type, [InvoiceType::Customer, InvoiceType::CustomerMaster], true), 403);

        $invoice->load([
            'checkout.orders.items.product.images',
            'order.items.product.images',
            'order.seller.sellerProfile',
            'order.seller',
        ]);

        $seller = $invoice->order?->seller;
        $profile = $seller?->sellerProfile;
        $sellerContact = null;

        if ($seller) {
            $address = collect([
                $profile?->business_address,
                $seller->residential_address,
            ])->map(fn ($v) => is_string($v) ? trim($v) : '')
                ->first(fn (string $v) => $v !== '') ?: null;

            $location = collect([
                $seller->digital_address,
                collect([$seller->city, $seller->region])->filter()->implode(', '),
            ])->map(fn ($v) => is_string($v) ? trim($v) : '')
                ->filter()
                ->unique()
                ->implode(' · ') ?: null;

            $phone = collect([$seller->mobile, $seller->whatsapp])
                ->map(fn ($v) => is_string($v) ? trim($v) : '')
                ->first(fn (string $v) => $v !== '') ?: null;

            $sellerContact = [
                'store_name' => $profile?->displayName() ?? $seller->name ?? 'Seller',
                'address' => $address,
                'location' => $location,
                'phone' => $phone,
            ];
        }

        $invoice->setAttribute('line_items', $this->lineItemsWithImages($invoice));

        return Inertia::render('shop/invoice-show', [
            'invoice' => $invoice,
            'sellerContact' => $sellerContact,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItemsWithImages(Invoice $invoice): array
    {
        /** @var Collection<int, OrderItem> $orderItems */
        $orderItems = collect();

        if ($invoice->order) {
            $orderItems = $invoice->order->items;
        } elseif ($invoice->checkout) {
            $orderItems = $invoice->checkout->orders->flatMap(fn ($order) => $order->items);
        }

        $pool = $orderItems->values();

        return collect($invoice->line_items ?? [])->map(function (array $line) use (&$pool) {
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
            } else {
                $line['image'] = $line['image'] ?? null;
            }

            return $line;
        })->values()->all();
    }
}
