<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaystackService;
use App\Services\WalletService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private OrderService $orderService,
        private WithdrawalPayoutService $withdrawalPayouts,
    ) {}

    public function handle(Request $request): Response
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (! $this->paystack->verifyWebhookSignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.failed' && $data) {
            Log::info('Paystack charge.failed', [
                'reference' => $data['reference'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            return response('OK', 200);
        }

        if ($event === 'charge.success' && $data) {
            try {
                $metadata = $data['metadata'] ?? [];
                $paidAmount = $this->paystack->paidAmountGhs($data);

                if (($metadata['type'] ?? '') === 'wallet_topup') {
                    $userId = (int) ($metadata['user_id'] ?? 0);
                    $method = (string) ($metadata['method'] ?? 'momo');
                    $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;

                    if ($expected !== null && ! $this->paystack->amountsMatch($paidAmount, $expected)) {
                        Log::warning('Paystack wallet top-up amount mismatch', [
                            'reference' => $data['reference'] ?? null,
                            'paid' => $paidAmount,
                            'expected' => $expected,
                        ]);

                        return response('OK', 200);
                    }

                    if ($userId > 0 && $paidAmount > 0) {
                        WalletService::creditFromVerifiedTopUp($userId, $paidAmount, $data['reference'], $method);
                    }

                    return response('OK', 200);
                }

                $checkoutId = $metadata['checkout_id'] ?? null;

                if ($checkoutId) {
                    $checkout = Checkout::find($checkoutId);
                    if ($checkout) {
                        $this->orderService->fulfillPaidCheckout($checkout, $data['reference'], $paidAmount);

                        return response('OK', 200);
                    }
                }

                $orderId = $metadata['order_id'] ?? null;
                $order = $orderId
                    ? Order::find($orderId)
                    : Order::where('payment_reference', $data['reference'])->first();

                if ($order) {
                    if ($order->checkout_id) {
                        $this->orderService->fulfillPaidCheckout($order->checkout, $data['reference'], $paidAmount);
                    } else {
                        $this->orderService->fulfillPaidOrder($order, $data['reference']);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Paystack webhook error', ['error' => $e->getMessage()]);
            }
        }

        if (in_array($event, ['transfer.success', 'transfer.failed', 'transfer.reversed'], true) && $data) {
            try {
                $this->withdrawalPayouts->handleTransferWebhook($data);
            } catch (\Throwable $e) {
                Log::error('Paystack transfer webhook error', ['error' => $e->getMessage()]);
            }
        }

        return response('OK', 200);
    }
}
