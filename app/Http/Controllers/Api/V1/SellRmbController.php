<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SellRmbTransfer;
use App\Services\SellRmbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellRmbController extends Controller
{
    public function __construct(private SellRmbService $sellRmb) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

        $transfers = SellRmbTransfer::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (SellRmbTransfer $item) => $this->sellRmb->transferPayload($item));

        return response()->json([
            'config' => $this->sellRmb->configPayload(),
            'transfers' => $transfers,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

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

    public function store(Request $request): JsonResponse
    {
        $this->assertBuyer($request);

        $transfer = $this->sellRmb->create($request->user(), $request);

        return response()->json([
            'message' => 'Sell RMB submitted.',
            'data' => $this->sellRmb->transferPayload($transfer),
        ], 201);
    }

    public function show(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        $this->assertOwner($request, $sellRmbTransfer);

        return response()->json([
            'data' => $this->sellRmb->transferPayload($sellRmbTransfer),
        ]);
    }

    public function cancel(Request $request, SellRmbTransfer $sellRmbTransfer): JsonResponse
    {
        $this->assertOwner($request, $sellRmbTransfer);

        $transfer = $this->sellRmb->cancel($sellRmbTransfer, $request->user(), $request->input('note'));

        return response()->json([
            'message' => 'Sell RMB cancelled.',
            'data' => $this->sellRmb->transferPayload($transfer),
        ]);
    }

    private function assertBuyer(Request $request): void
    {
        abort_unless($request->user()?->isBuyer(), 403);
    }

    private function assertOwner(Request $request, SellRmbTransfer $transfer): void
    {
        abort_unless($request->user() && (int) $transfer->user_id === (int) $request->user()->id, 403);
    }
}
