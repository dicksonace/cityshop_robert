<?php

namespace App\Http\Controllers;

use App\Models\JunipayPayment;
use App\Services\JunipayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * CityShop's own "hosted" page for Junipay: ask for the MoMo number, send the prompt, wait.
 * All three routes are signed, so only the link CityShop handed out works.
 */
class JunipayPayController extends Controller
{
    public function __construct(private JunipayService $junipay) {}

    public function show(string $reference): View
    {
        $payment = JunipayPayment::query()->where('reference', $reference)->firstOrFail();
        $expires = now()->addHours(2);

        return view('junipay.pay', [
            'payment' => $payment,
            'title' => (string) ($payment->meta['title'] ?? 'CityShop payment'),
            'network' => match (strtolower((string) ((is_array($payment->meta) ? ($payment->meta['momo_network'] ?? null) : null) ?: $payment->provider))) {
                'vodafone', 'telecel' => 'telecel',
                'airteltigo', 'airtel', 'tigo' => 'airteltigo',
                default => 'mtn',
            },
            'startUrl' => URL::temporarySignedRoute('junipay.pay.start', $expires, ['reference' => $reference]),
            'statusUrl' => URL::temporarySignedRoute('junipay.pay.status', $expires, ['reference' => $reference]),
            'cancelUrl' => $this->returnUrl($payment, 'cancelled'),
        ]);
    }

    public function start(Request $request, string $reference): JsonResponse
    {
        $payment = JunipayPayment::query()->where('reference', $reference)->firstOrFail();
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
        ]);

        try {
            $payment = $this->junipay->sendPrompt($payment, $data['phone'], $data['network']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::warning('Junipay prompt failed', ['reference' => $reference, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->statusPayload($payment);
    }

    public function status(string $reference, JunipayWebhookController $settler): JsonResponse
    {
        $payment = JunipayPayment::query()->where('reference', $reference)->firstOrFail();

        if (filled($payment->trans_id) && $payment->status !== 'failed') {
            try {
                $settler->settle($reference);
            } catch (\Throwable $e) {
                Log::info('Junipay status poll failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            }
            $payment->refresh();
        }

        return $this->statusPayload($payment);
    }

    private function statusPayload(JunipayPayment $payment): JsonResponse
    {
        return response()->json([
            'status' => $payment->status,
            'message' => $payment->message,
            'redirect' => $payment->status === 'success' ? $this->returnUrl($payment, 'successful') : null,
        ]);
    }

    private function returnUrl(JunipayPayment $payment, string $status): string
    {
        $base = (string) ($payment->redirect_url ?: url('/api/v1/flutterwave/mobile-return'));

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query([
            'tx_ref' => $payment->reference,
            'reference' => $payment->reference,
            'status' => $status,
        ]);
    }
}
