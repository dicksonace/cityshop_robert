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
        $ids = self::callbackIds($request->all());
        $transId = (string) ($request->input('trans_id') ?? $request->input('transID') ?? data_get($request->all(), 'info.transID') ?? '');

        if ($ids === [] || ! $this->junipay->isConfigured()) {
            return response('Received', 200);
        }

        try {
            $payment = null;
            foreach ($ids as $id) {
                $payment = JunipayPayment::query()
                    ->where('reference', JunipayService::referenceFromForeignId($id))
                    ->first();
                if ($payment) {
                    break;
                }
            }
            if ($payment) {
                $reference = $payment->reference;
                if (filled($payment->trans_id) && $transId !== '' && ! $this->junipay->ownsTransaction($payment, $transId)) {
                    Log::warning('Junipay callback ignored: transaction ID does not match', ['reference' => $reference]);
                } else {
                    $this->settle($reference);
                }

                return response('Received', 200);
            }

            $withdrawal = Withdrawal::query()
                ->where(function ($query) use ($ids) {
                    $query->whereIn('junipay_reference', $ids)
                        ->orWhereIn('junipay_trans_id', $ids);
                })
                ->first();
            if ($withdrawal) {
                $this->payouts->handleJunipayCallback($withdrawal, $request->all());
            } else {
                Log::info('Junipay callback matched no payment or withdrawal', ['ids' => $ids]);
            }
        } catch (\Throwable $e) {
            Log::error('Junipay webhook error', ['error' => $e->getMessage(), 'ids' => $ids]);
        }

        return response('Received', 200);
    }

    /**
     * Junipay sends our reference as partnerTransID and its own transfer id as foreignID.
     * Either value, including when nested under info, is enough to find the row.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function callbackIds(array $data): array
    {
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            [
                $data['foreignID'] ?? '',
                $data['partnerTransID'] ?? '',
                $data['transID'] ?? '',
                $data['trans_id'] ?? '',
                $data['reference'] ?? '',
                $info['foreignID'] ?? '',
                $info['partnerTransID'] ?? '',
                $info['transID'] ?? '',
                $info['trans_id'] ?? '',
            ],
        ), fn (string $value) => $value !== '')));
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
