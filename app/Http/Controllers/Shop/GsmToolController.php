<?php

namespace App\Http\Controllers\Shop;

use App\Enums\GsmServiceType;
use App\Http\Controllers\Controller;
use App\Models\GsmOrder;
use App\Models\GsmService;
use App\Services\GsmToolService;
use App\Services\KycService;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GsmToolController extends Controller
{
    public function __construct(private GsmToolService $gsm) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('shop/gsm-tools/index', [
            'services' => $this->gsm->activeServices()->map(fn (GsmService $s) => $this->gsm->servicePayload($s))->values()->all(),
            'groups' => $this->gsm->catalogGroups(),
            'serviceTypes' => GsmServiceType::options(),
            'wallet' => $user ? WalletService::ensure($user)->toFrontendArray() : null,
        ]);
    }

    public function history(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $page = $this->gsm->paginatedBuyerOrders($user, $request);

        return Inertia::render('shop/gsm-tools/history', [
            'orders' => $page['paginator']->through(fn (GsmOrder $order) => $this->gsm->orderPayload($order)),
        ]);
    }

    public function showService(Request $request, GsmService $gsmService): Response|RedirectResponse
    {
        abort_unless($gsmService->active, 404);

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('shop/gsm-tools/order', [
            'service' => $this->gsm->servicePayload($gsmService->load(['activeFields', 'group'])),
            'wallet' => WalletService::ensure($user)->toFrontendArray(),
            'hasPaymentPin' => PaymentPinService::hasPin($user),
            'kyc' => KycService::payload($user, withPhotos: false),
            'contactEmail' => $user->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($denied = KycService::denyStoreFundsRedirect($user)) {
            return $denied;
        }

        $request->validate([
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($user, $request->input('payment_pin'));

        $order = $this->gsm->createOrder($user, $request);

        return redirect()
            ->route('gsm-tools.orders.show', $order)
            ->with('success', 'Order placed. Deducted from your wallet.');
    }

    public function showOrder(Request $request, GsmOrder $gsmOrder): Response
    {
        abort_unless($request->user() && (int) $gsmOrder->user_id === (int) $request->user()->id, 403);

        return Inertia::render('shop/gsm-tools/show', [
            'order' => $this->gsm->orderPayload($gsmOrder, withHistory: true, buyerView: true),
        ]);
    }

    public function cancel(Request $request, GsmOrder $gsmOrder): RedirectResponse
    {
        abort(403, 'Only an admin can cancel this GSM order.');
    }
}
