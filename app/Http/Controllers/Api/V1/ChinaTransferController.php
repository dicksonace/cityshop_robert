<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Services\ChinaTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChinaTransferController extends Controller
{
    public function __construct(private ChinaTransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

        $transfers = ChinaTransfer::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ChinaTransfer $item) => $this->transfers->transferPayload($item));

        return response()->json([
            'config' => $this->transfers->configPayload(),
            'wallet' => \App\Services\WalletService::ensure($request->user())->toFrontendArray(),
            'transfers' => $transfers,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

        $validated = $request->validate([
            'ghs_amount' => ['required', 'numeric', 'min:1'],
        ]);

        return response()->json([
            'data' => $this->transfers->quote((float) $validated['ghs_amount']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

        if ($denied = \App\Services\RmbWalletGuard::denyJson($request->user())) {
            return $denied;
        }

        $request->validate([
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'funding_source' => ['nullable', 'in:external,rmb_wallet'],
        ]);

        \App\Services\PaymentPinService::assertValidForAction(
            $request->user(),
            $request->input('payment_pin'),
        );

        $transfer = $this->transfers->create($request->user(), $request);

        return response()->json([
            'message' => 'Transfer submitted.',
            'data' => $this->transfers->transferPayload($transfer),
        ], 201);
    }

    public function show(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        $this->assertOwner($request, $chinaTransfer);

        return response()->json([
            'data' => $this->transfers->transferPayload($chinaTransfer),
        ]);
    }

    public function cancel(Request $request, ChinaTransfer $chinaTransfer): JsonResponse
    {
        $this->assertOwner($request, $chinaTransfer);

        $transfer = $this->transfers->cancel($chinaTransfer, $request->user(), $request->input('note'));

        return response()->json([
            'message' => 'Transfer cancelled.',
            'data' => $this->transfers->transferPayload($transfer),
        ]);
    }

    private function assertBuyer(Request $request): void
    {
        abort_unless($request->user()?->isBuyer(), 403);
    }

    private function assertOwner(Request $request, ChinaTransfer $transfer): void
    {
        abort_unless($request->user() && (int) $transfer->user_id === (int) $request->user()->id, 403);
    }
}
