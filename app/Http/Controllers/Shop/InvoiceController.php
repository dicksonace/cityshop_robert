<?php

namespace App\Http\Controllers\Shop;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->type, [InvoiceType::Customer, InvoiceType::CustomerMaster], true), 403);

        $invoice->load([
            'checkout',
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

        return Inertia::render('shop/invoice-show', [
            'invoice' => $invoice,
            'sellerContact' => $sellerContact,
        ]);
    }
}
