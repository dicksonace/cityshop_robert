<?php

namespace App\Http\Controllers\Shop;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\BuyerInvoicePrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function __construct(private BuyerInvoicePrintService $printService) {}

    public function show(Request $request, Invoice $invoice): InertiaResponse|RedirectResponse|View
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->type, [InvoiceType::Customer, InvoiceType::CustomerMaster], true), 403);

        if ($request->boolean('print') || $request->query('print') === '1') {
            return $this->printView($invoice);
        }

        $sellerContacts = $this->printService->resolveSellerContacts($invoice);
        $buyerShipTo = $this->printService->resolveBuyerShipTo($invoice);
        $fees = $this->printService->resolveFees($invoice);
        $invoice->setAttribute('line_items', $this->printService->lineItemsWithImages($invoice));
        $invoice->loadMissing(['checkout', 'order']);

        return Inertia::render('shop/invoice-show', [
            'invoice' => $invoice,
            'sellerContacts' => $sellerContacts,
            'sellerContact' => $sellerContacts[0] ?? null,
            'buyerShipTo' => $buyerShipTo,
            'deliveryFees' => $fees['delivery'],
            'shippingFees' => $fees['shipping'],
        ]);
    }

    public function print(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->type, [InvoiceType::Customer, InvoiceType::CustomerMaster], true), 403);

        return $this->printView($invoice);
    }

    private function printView(Invoice $invoice): View
    {
        $data = $this->printService->payload($invoice);
        $data['lineItems'] = $this->printService->lineItemsWithImages($invoice, forPdf: false);

        return view('invoices.buyer-web', $data);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless(in_array($invoice->type, [InvoiceType::Customer, InvoiceType::CustomerMaster], true), 403);

        return $this->printService->pdf($invoice);
    }
}
