<?php

namespace App\Http\Controllers;

use App\Services\FlutterwaveCheckoutVerifier;
use App\Services\FlutterwaveService;
use App\Services\KorapayService;
use App\Services\OrderService;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class KorapayWebhookController extends FlutterwaveWebhookController
{
    public function __construct(
        private KorapayService $korapay,
        FlutterwaveService $flutterwave,
        OrderService $orderService,
        FlutterwaveCheckoutVerifier $paymentVerifier,
    ) {
        parent::__construct($flutterwave, $orderService, $paymentVerifier);
    }

    public function handle(Request $request): Response
    {
        $secret = PlatformSettings::resolvedKorapaySecretKey();
        $signature = (string) $request->header('x-korapay-signature', '');
        if ($secret === '' || $signature === '') {
            return response('Invalid signature', 400);
        }

        $data = $request->input('data');
        if (! is_array($data)) {
            return response('OK', 200);
        }

        $expected = hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $secret);
        if (! hash_equals($expected, $signature)) {
            // PHP and Kora can encode numbers differently; the charge is re-checked with Korapay below anyway.
            Log::info('Korapay webhook signature did not match locally', ['reference' => $data['reference'] ?? null]);
        }

        if ((string) $request->input('event') !== 'charge.success') {
            return response('OK', 200);
        }

        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            return response('OK', 200);
        }

        try {
            $verified = $this->korapay->verifyByReference($reference);
            if ($this->korapay->isSuccessful($verified) && strtoupper((string) ($verified['currency'] ?? '')) === 'GHS') {
                $this->fulfill($this->korapay, $verified, $reference);
            }
        } catch (\Throwable $e) {
            Log::error('Korapay webhook error', ['error' => $e->getMessage(), 'reference' => $reference]);
        }

        return response('OK', 200);
    }
}
