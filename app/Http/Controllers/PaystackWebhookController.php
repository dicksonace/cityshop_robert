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

        if ($event === 'charge.success' && $data) {
            try {
                $metadata = $data['metadata'] ?? [];

                if (($metadata['type'] ?? '') === 'wallet_topup') {
                    $userId = (int) ($metadata['user_id'] ?? 0);
                    $amount = round(((int) ($data['amount'] ?? 0)) / 100, 2);
                    $method = (string) ($metadata['method'] ?? 'momo');

                    if ($userId > 0 && $amount > 0) {
                        WalletService::creditFromVerifiedTopUp($userId, $amount, $data['reference'], $method);
                    }

                    return response('OK', 200);
                }

                $checkoutId = $data['metadata']['checkout_id'] ?? null;

                if ($checkoutId) {
                    $checkout = Checkout::find($checkoutId);
                    if ($checkout) {
                        $this->orderService->fulfillPaidCheckout($checkout, $data['reference']);

                        return response('OK', 200);
                    }
                }

                $orderId = $data['metadata']['order_id'] ?? null;
                $order = $orderId
                    ? Order::find($orderId)
                    : Order::where('payment_reference', $data['reference'])->first();

                if ($order) {
                    if ($order->checkout_id) {
                        $this->orderService->fulfillPaidCheckout($order->checkout, $data['reference']);
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
