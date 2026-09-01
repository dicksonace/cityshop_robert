<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Services\ChinaTransferService;
use App\Services\KycService;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChinaTransferController extends Controller
{
    public function __construct(private ChinaTransferService $transfers) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        $transfers = ChinaTransfer::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ChinaTransfer $item) => $this->transfers->transferPayload($item));

        return Inertia::render('shop/china-transfer/index', [
            'config' => $this->transfers->configPayload(),
            'wallet' => WalletService::ensure($request->user())->toFrontendArray(),
            'transfers' => $transfers,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        if (! $this->transfers->isOpen()) {
            return redirect()->route('wallet.china-transfer.index')
                ->with('error', 'Transfer to China is not available right now.');
        }

        return Inertia::render('shop/china-transfer/create', [
            'config' => $this->transfers->configPayload(),
            'wallet' => WalletService::ensure($request->user())->toFrontendArray(),
            'hasPaymentPin' => PaymentPinService::hasPin($request->user()),
            'kyc' => KycService::payload($request->user(), withPhotos: false),
            'initialGhs' => $request->query('ghs_amount'),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        $validated = $request->validate([
            'ghs_amount' => ['required', 'numeric', 'min:1'],
        ]);

        return response()->json([
            'data' => $this->transfers->quote((float) $validated['ghs_amount']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        if ($denied = \App\Services\RmbWalletGuard::denyRedirect($request->user())) {
            return $denied;
        }

        $request->validate([
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        \App\Services\PaymentPinService::assertValidForAction(
            $request->user(),
            $request->input('payment_pin'),
        );

        $transfer = $this->transfers->create($request->user(), $request);

        return redirect()
            ->route('wallet.china-transfer.show', $transfer)
            ->with('success', 'Transfer submitted. Track status on this page.');
    }

    public function show(Request $request, ChinaTransfer $chinaTransfer): Response|JsonResponse
    {
        abort_unless($request->user() && (int) $chinaTransfer->user_id === (int) $request->user()->id, 403);

        $payload = $this->transfers->transferPayload($chinaTransfer);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('shop/china-transfer/show', [
            'transfer' => $payload,
        ]);
    }

    public function cancel(Request $request, ChinaTransfer $chinaTransfer): RedirectResponse
    {
        abort_unless($request->user() && (int) $chinaTransfer->user_id === (int) $request->user()->id, 403);

        $this->transfers->cancel($chinaTransfer, $request->user(), $request->input('note'));

        return back()->with('success', 'Transfer cancelled.');
    }
}
