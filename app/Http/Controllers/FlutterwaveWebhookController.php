<?php

namespace App\Http\Controllers;

use App\Contracts\HostedPaymentGateway;
use App\Http\Controllers\Api\V1\QrPaymentController;
use App\Models\Checkout;
use App\Models\User;
use App\Services\FlutterwaveCheckoutVerifier;
use App\Services\FlutterwaveService;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        private FlutterwaveService $flutterwave,
        protected OrderService $orderService,
        protected FlutterwaveCheckoutVerifier $paymentVerifier,
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
            $this->fulfill($this->flutterwave, $data, (string) ($data['tx_ref'] ?? ''));
        } catch (\Throwable $e) {
            Log::error('Flutterwave webhook error', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    /**
     * Give value for a verified, successful hosted-gateway charge.
     *
     * @param  array<string, mixed>  $data
     */
    protected function fulfill(HostedPaymentGateway $gateway, array $data, string $reference): void
    {
        $label = ucfirst($gateway->name());
        $meta = $gateway->normalizeMeta($data);
        $paidAmount = $gateway->paidAmountGhs($data);

        if (($meta['type'] ?? '') === 'wallet_topup') {
            $userId = (int) ($meta['user_id'] ?? 0);
            $method = (string) ($meta['method'] ?? 'momo');
            $expected = isset($meta['expected_amount']) ? (float) $meta['expected_amount'] : null;
            $credit = $gateway->topUpCreditFromMetadata($meta, $paidAmount);

            if ($expected !== null && ! $gateway->amountsMatch($paidAmount, $expected)) {
                Log::warning($label.' wallet top-up amount mismatch', [
                    'reference' => $reference,
                    'paid' => $paidAmount,
                    'expected' => $expected,
                ]);

                return;
            }

            if ($userId > 0 && $credit > 0 && $reference !== '') {
                WalletService::creditFromVerifiedTopUp($userId, $credit, $reference, $method);
            }

            return;
        }

        if (($meta['type'] ?? '') === 'qr_direct_pay') {
            $payer = User::query()->find((int) ($meta['user_id'] ?? 0));
            if ($payer && $reference !== '') {
                try {
                    QrPaymentController::settleFlutterwave($payer, $reference, $gateway);
                } catch (\Throwable $e) {
                    Log::warning($label.' QR direct pay failed', [
                        'reference' => $reference,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        $checkoutId = (int) ($meta['checkout_id'] ?? 0);
        if ($checkoutId > 0 && $reference !== '') {
            $checkout = Checkout::find($checkoutId);
            if ($checkout) {
                try {
                    $this->paymentVerifier->verifyForCheckout($checkout, $reference, $data, $gateway);
                    $this->orderService->fulfillPaidCheckout($checkout, $reference, $paidAmount);
                    $this->paymentVerifier->forgetPending($checkout);
                } catch (ValidationException $e) {
                    Log::warning($label.' webhook rejected checkout payment', [
                        'checkout_id' => $checkout->id,
                        'errors' => $e->errors(),
                    ]);
                }
            }
        }

        // Draft payments: fulfilled when the buyer's app or browser verifies with PendingCheckoutDraft.
        if (($meta['draft'] ?? false) || ($meta['source'] ?? '') === 'mobile_app_draft') {
            Log::info($label.' draft charge completed', [
                'reference' => $reference,
                'buyer_id' => $meta['buyer_id'] ?? null,
            ]);
        }
    }
}
