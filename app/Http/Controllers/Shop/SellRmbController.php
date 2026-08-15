<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\SellRmbTransfer;
use App\Services\SellRmbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellRmbController extends Controller
{
    public function __construct(private SellRmbService $sellRmb) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $transfers = SellRmbTransfer::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SellRmbTransfer $item) => $this->sellRmb->transferPayload($item));

        return Inertia::render('shop/sell-rmb/index', [
            'config' => $this->sellRmb->configPayload(),
            'transfers' => $transfers,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);

        if (! $this->sellRmb->isOpen()) {
            return redirect()->route('wallet.sell-rmb.index')
                ->with('error', 'Sell RMB is not available right now.');
        }

        return Inertia::render('shop/sell-rmb/create', [
            'config' => $this->sellRmb->configPayload(),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $validated = $request->validate([
            'rmb_amount' => ['required', 'numeric', 'min:1'],
            'payout_currency' => ['nullable', 'in:usd,ghs'],
        ]);

        return response()->json([
            'data' => $this->sellRmb->quote(
                (float) $validated['rmb_amount'],
                $validated['payout_currency'] ?? 'usd',
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $transfer = $this->sellRmb->create($request->user(), $request);

        return redirect()
            ->route('wallet.sell-rmb.show', $transfer)
            ->with('success', 'Sell RMB submitted. Track status on this page.');
    }

    public function show(Request $request, SellRmbTransfer $sellRmbTransfer): Response|JsonResponse
    {
        abort_unless($request->user() && (int) $sellRmbTransfer->user_id === (int) $request->user()->id, 403);

        $payload = $this->sellRmb->transferPayload($sellRmbTransfer);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('shop/sell-rmb/show', [
            'transfer' => $payload,
        ]);
    }

    public function cancel(Request $request, SellRmbTransfer $sellRmbTransfer): RedirectResponse
    {
        abort_unless($request->user() && (int) $sellRmbTransfer->user_id === (int) $request->user()->id, 403);

        $this->sellRmb->cancel($sellRmbTransfer, $request->user(), $request->input('note'));

        return back()->with('success', 'Sell RMB cancelled.');
    }
}
