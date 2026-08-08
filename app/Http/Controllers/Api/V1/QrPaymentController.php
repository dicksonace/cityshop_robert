<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentPinService;
use App\Services\QrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QrPaymentController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1', 'max:50000'],
            'reason' => ['nullable', 'string', 'max:80'],
        ]);

        $amount = array_key_exists('amount', $validated) && $validated['amount'] !== null
            ? (float) $validated['amount']
            : null;

        $reason = array_key_exists('reason', $validated) && is_string($validated['reason'] ?? null)
            ? trim((string) $validated['reason'])
            : null;

        return response()->json([
            'data' => QrPaymentService::receiveCode($request->user(), $amount, $reason),
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $data = QrPaymentService::resolvePayload($validated['payload'], $request->user());
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $data]);
    }

    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'note' => ['nullable', 'string', 'max:120'],
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);

        try {
            $result = QrPaymentService::pay(
                $request->user(),
                $validated['payload'],
                (float) $validated['amount'],
                $validated['note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wallet = $request->user()->wallet?->fresh();

        return response()->json([
            'message' => 'Payment sent.',
            'data' => $result,
            'wallet' => $wallet ? [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
            ] : null,
        ], 201);
    }
}
