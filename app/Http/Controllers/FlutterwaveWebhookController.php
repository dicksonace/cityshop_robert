<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use App\Services\FlutterwaveCheckoutVerifier;
use App\Services\FlutterwaveService;
use App\Services\OrderService;
use App\Services\WalletService;
use App\Support\PendingCheckoutDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        private FlutterwaveService $flutterwave,
        private OrderService $orderService,
        private FlutterwaveCheckoutVerifier $paymentVerifier,
    ) {}

    public function handle(Request $request): Response
    {
        $hash = $request->header('verif-hash');
        if (! $this->flutterwave->verifyWebhookSignature($hash)) {
            return response('Invalid signature', 400);
        }

        $event = (string) $request->input('event');
        $data = $request->input('data');
        if (! is_array($data)) {
            return response('OK', 200);
        }

        if (! in_array($event, ['charge.completed', 'charge.successful'], true)
            && strtolower((string) ($data['status'] ?? '')) !== 'successful') {
            return response('OK', 200);
        }

        if (! $this->flutterwave->isSuccessful($data) && strtolower((string) ($data['status'] ?? '')) !== 'successful') {
            return response('OK', 200);
        }

        try {
            $meta = $this->flutterwave->normalizeMeta($data);
            $reference = (string) ($data['tx_ref'] ?? '');
            $paidAmount = $this->flutterwave->paidAmountGhs($data);

            if (($meta['type'] ?? '') === 'wallet_topup') {
                $userId = (int) ($meta['user_id'] ?? 0);
                $method = (string) ($meta['method'] ?? 'momo');
                $expected = isset($meta['expected_amount']) ? (float) $meta['expected_amount'] : null;
                $credit = $this->flutterwave->topUpCreditFromMetadata($meta, $paidAmount);

                if ($expected !== null && ! $this->flutterwave->amountsMatch($paidAmount, $expected)) {
                    Log::warning('Flutterwave wallet top-up amount mismatch', [
                        'reference' => $reference,
                        'paid' => $paidAmount,
                        'expected' => $expected,
                    ]);

                    return response('OK', 200);
                }

                if ($userId > 0 && $credit > 0 && $reference !== '') {
                    WalletService::creditFromVerifiedTopUp($userId, $credit, $reference, $method);
                }

                return response('OK', 200);
            }

            $checkoutId = (int) ($meta['checkout_id'] ?? 0);
            if ($checkoutId > 0 && $reference !== '') {
                $checkout = Checkout::find($checkoutId);
                if ($checkout) {
                    try {
                        $this->paymentVerifier->verifyForCheckout($checkout, $reference, $data);
                        $this->orderService->fulfillPaidCheckout($checkout, $reference, $paidAmount);
                        $this->paymentVerifier->forgetPending($checkout);
                    } catch (ValidationException $e) {
                        Log::warning('Flutterwave webhook rejected checkout payment', [
                            'checkout_id' => $checkout->id,
                            'errors' => $e->errors(),
                        ]);
                    }
                }
            }

            // Draft payments: fulfilled when the app verifies with PendingCheckoutDraft.
            if (($meta['draft'] ?? false) || ($meta['source'] ?? '') === 'mobile_app_draft') {
                Log::info('Flutterwave draft charge completed', [
                    'reference' => $reference,
                    'buyer_id' => $meta['buyer_id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Flutterwave webhook error', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }
}
