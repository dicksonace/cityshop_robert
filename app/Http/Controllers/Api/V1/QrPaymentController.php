<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveService;
use App\Services\PaymentPinService;
use App\Services\PaystackService;
use App\Services\QrPaymentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function initializeGateway(Request $request, PaystackService $paystack, FlutterwaveService $flutterwave): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'note' => ['nullable', 'string', 'max:120'],
            'method' => ['required', 'in:momo,card'],
            'gateway' => ['required', 'in:paystack,flutterwave'],
        ]);

        $payer = $request->user();
        $resolved = QrPaymentService::resolvePayload($validated['payload'], $payer);
        $amount = round((float) $validated['amount'], 2);

        if ($resolved['amount'] !== null && abs($resolved['amount'] - $amount) > 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['This QR code is for GH₵'.number_format($resolved['amount'], 2).'.'],
            ]);
        }

        $gateway = $validated['gateway'];
        $callbackUrl = $gateway === 'flutterwave'
            ? url('/api/v1/flutterwave/mobile-return')
            : url('/api/v1/paystack/mobile-return');

        $extra = [
            'type' => 'qr_direct_pay',
            'recipient_id' => (int) $resolved['user']['id'],
            'transfer_note' => $validated['note'] ?? ($resolved['reason'] ?? null),
        ];

        try {
            $data = $gateway === 'flutterwave'
                ? $flutterwave->initializeWalletTopUp($payer, $amount, $validated['method'], $callbackUrl, null, $extra)
                : $paystack->initializeWalletTopUp($payer, $amount, $validated['method'], $callbackUrl, null, $extra);
        } catch (\Throwable $e) {
            Log::error('QR direct pay init failed', ['error' => $e->getMessage()]);
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Could not start payment. Please try again.';

            return response()->json(['message' => $message], 500);
        }

        return response()->json([
            'authorization_url' => $data['authorization_url'],
            'reference' => $data['reference'],
            'callback_url' => $callbackUrl,
            'amount' => $data['credit'],
            'fee' => $data['fee'],
            'charge' => $data['charge'],
            'currency' => 'GHS',
            'gateway' => $gateway,
            'recipient' => $resolved['user'],
        ]);
    }

    public function verifyGateway(Request $request, PaystackService $paystack, FlutterwaveService $flutterwave): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'gateway' => ['required', 'in:paystack,flutterwave'],
        ]);

        try {
            $result = $validated['gateway'] === 'flutterwave'
                ? self::settleFlutterwave($request->user(), $validated['reference'], $flutterwave)
                : self::settlePaystack($request->user(), $validated['reference'], $paystack);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wallet = WalletService::ensure($request->user());

        return response()->json([
            'message' => 'Payment sent.',
            'data' => $result,
            'wallet' => [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function settleFromMetadata(User $payer, array $metadata, float $paid, float $credit, string $reference, string $method): array
    {
        if ((int) ($metadata['user_id'] ?? 0) !== $payer->id) {
            throw new \RuntimeException('Payment does not belong to your account.');
        }

        if ($credit < 5) {
            throw new \RuntimeException('Invalid payment amount.');
        }

        $recipientId = (int) ($metadata['recipient_id'] ?? 0);
        if ($recipientId < 1) {
            throw new \RuntimeException('This payment is missing the person to pay.');
        }

        $note = isset($metadata['transfer_note']) && is_string($metadata['transfer_note'])
            ? $metadata['transfer_note']
            : null;

        return QrPaymentService::settleGatewayPayment($payer, $recipientId, $credit, $reference, $method, $note);
    }

    /**
     * @return array<string, mixed>
     */
    public static function settlePaystack(User $payer, string $reference, PaystackService $paystack): array
    {
        $data = $paystack->verifyTransaction($reference);
        if (($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Payment was not successful.');
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        if (($metadata['type'] ?? '') !== 'qr_direct_pay') {
            throw new \RuntimeException('Invalid QR payment.');
        }

        $paid = round(((int) ($data['amount'] ?? 0)) / 100, 2);
        $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
        if ($expected !== null && ! $paystack->amountsMatch($paid, $expected)) {
            throw new \RuntimeException('Payment amount could not be verified.');
        }

        $credit = $paystack->topUpCreditFromMetadata($metadata, $paid);

        return self::settleFromMetadata(
            $payer,
            $metadata,
            $paid,
            $credit,
            $reference,
            (string) ($metadata['method'] ?? 'momo'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function settleFlutterwave(User $payer, string $reference, FlutterwaveService $flutterwave): array
    {
        $data = $flutterwave->verifyByReference($reference);
        if (! $flutterwave->isSuccessful($data)) {
            throw new \RuntimeException('Payment was not successful.');
        }

        $metadata = $flutterwave->normalizeMeta($data);
        if (($metadata['type'] ?? '') !== 'qr_direct_pay') {
            throw new \RuntimeException('Invalid QR payment.');
        }

        $paid = $flutterwave->paidAmountGhs($data);
        $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
        if ($expected !== null && ! $flutterwave->amountsMatch($paid, $expected)) {
            throw new \RuntimeException('Payment amount could not be verified.');
        }

        $credit = $flutterwave->topUpCreditFromMetadata($metadata, $paid);

        return self::settleFromMetadata(
            $payer,
            $metadata,
            $paid,
            $credit,
            $reference,
            (string) ($metadata['method'] ?? 'momo'),
        );
    }
}
