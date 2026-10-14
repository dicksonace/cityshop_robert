<?php

namespace App\Http\Controllers;

use App\Models\JunipayPayment;
use App\Models\Withdrawal;
use App\Services\FlutterwaveCheckoutVerifier;
use App\Services\FlutterwaveService;
use App\Services\JunipayService;
use App\Services\OrderService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Junipay callbacks are not signed, so nothing here trusts the payload: collections are
 * re-checked with Junipay, and payouts must carry the transaction ID Junipay gave us.
 */
class JunipayWebhookController extends FlutterwaveWebhookController
{
    public function __construct(
        private JunipayService $junipay,
        private WithdrawalPayoutService $payouts,
        FlutterwaveService $flutterwave,
        OrderService $orderService,
        FlutterwaveCheckoutVerifier $paymentVerifier,
    ) {
        parent::__construct($flutterwave, $orderService, $paymentVerifier);
    }

    public function handle(Request $request): Response
    {
        $reference = trim((string) $request->input('foreignID', ''));
        $transId = (string) ($request->input('trans_id') ?? $request->input('transID') ?? '');

        if ($reference === '' || ! $this->junipay->isConfigured()) {
            return response('Received', 200);
        }

        try {
            $payment = JunipayPayment::query()
                ->where('reference', JunipayService::referenceFromForeignId($reference))
                ->first();
            if ($payment) {
                $reference = $payment->reference;
                if (filled($payment->trans_id) && $transId !== '' && ! $this->junipay->ownsTransaction($payment, $transId)) {
                    Log::warning('Junipay callback ignored: transaction ID does not match', ['reference' => $reference]);
                } else {
                    $this->settle($reference);
                }

                return response('Received', 200);
            }

            $withdrawal = Withdrawal::query()->where('junipay_reference', $reference)->first();
            if ($withdrawal) {
                $this->payouts->handleJunipayCallback($withdrawal, $request->all());
            }
        } catch (\Throwable $e) {
            Log::error('Junipay webhook error', ['error' => $e->getMessage(), 'reference' => $reference]);
        }

        return response('Received', 200);
    }

    /**
     * Ask Junipay for the collection and give value if it is paid. Safe to repeat.
     *
     * @return string success, failed or pending
     */
    public function settle(string $reference): string
    {
        $verified = $this->junipay->verifyByReference($reference);
        if ($this->junipay->isSuccessful($verified)) {
            $this->fulfill($this->junipay, $verified, $reference);
        }

        return strtolower((string) ($verified['status'] ?? 'pending'));
    }
}
