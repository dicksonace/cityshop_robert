<?php

namespace App\Services;

use App\Contracts\HostedPaymentGateway;
use App\Models\JunipayPayment;
use App\Models\User;
use App\Support\GhanaBanks;
use App\Support\PaymentReference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Junipay Direct API: Mobile Money collections (a prompt on the payer's phone) and payouts.
 *
 * Junipay has no hosted checkout page, so {@see initializePayment()} returns CityShop's own
 * /pay/junipay page, which asks for the MoMo number and waits for the payer to approve.
 */
class JunipayService implements HostedPaymentGateway
{
    /** Junipay bank codes, keyed by {@see GhanaBanks} slug. */
    private const FOREIGN_PREFIX = 'CSJ';

    public const BANK_CODES = [
        'access' => '2001',
        'absa' => '2002',
        'adb' => '2003',
        'arb_apex' => '2004',
        'bank_of_africa' => '2005',
        'bog' => '2006',
        'cal' => '2007',
        'cbg' => '2008',
        'ecobank' => '2009',
        'fidelity' => '2010',
        'first_atlantic' => '2011',
        'fnb' => '2012',
        'gcb' => '2013',
        'gtbank' => '2014',
        'nib' => '2015',
        'prudential' => '2016',
        'republic' => '2017',
        'stanbic' => '2018',
        'standard_chartered' => '2019',
        'omnibsic' => '2020',
        'uba' => '2021',
        'umb' => '2022',
        'zenith' => '2023',
        'firstbank' => '2024',
        'societe_generale' => '2025',
    ];

    public function name(): string
    {
        return 'junipay';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->privateKey() !== '';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured() && PlatformSettings::junipaySettings()['collections_enabled'];
    }

    public function payoutsAvailable(): bool
    {
        return $this->isConfigured() && PlatformSettings::junipaySettings()['payouts_enabled'];
    }

    public function unavailableMessage(): string
    {
        if ($this->isConfigured() && ! PlatformSettings::junipaySettings()['collections_enabled']) {
            return 'Junipay payment is temporarily disabled. Please use another payment option.';
        }

        return 'Junipay is not available right now. Please use another payment option.';
    }

    public function baseUrl(): string
    {
        return PlatformSettings::junipaySettings()['sandbox']
            ? 'https://sandbox.junipayments.com'
            : 'https://api.junipayments.com';
    }

    private function clientId(): string
    {
        return PlatformSettings::resolvedJunipayClientId();
    }

    private function privateKey(): string
    {
        return PlatformSettings::resolvedJunipayPrivateKey();
    }

    /** RS256 token Junipay expects in "Authorization: Bearer". */
    public function token(?string $clientId = null, ?string $privateKey = null): string
    {
        $clientId ??= $this->clientId();
        $key = openssl_pkey_get_private($privateKey ?? $this->privateKey());
        if ($key === false) {
            throw new \RuntimeException('The Junipay private key could not be read.');
        }

        $now = time();
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode(['clientId' => $clientId, 'iat' => $now, 'exp' => $now + 3600])),
        ];

        if (! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign the Junipay token.');
        }
        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private function client(?string $clientId = null, ?string $privateKey = null): PendingRequest
    {
        $clientId ??= $this->clientId();

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->token($clientId, $privateKey))
            ->withHeaders(['clientid' => $clientId])
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function probeCredentials(?string $clientId = null, ?string $privateKey = null): array
    {
        $clientId ??= $this->clientId();
        $privateKey ??= $this->privateKey();
        if ($clientId === '' || $privateKey === '') {
            return ['ok' => false, 'message' => 'Add the Junipay Client ID and RSA private key first.'];
        }

        try {
            $response = $this->client($clientId, $privateKey)->post('/accountbalance', ['country' => 'GH', 'currency' => 'GHS']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Junipay: '.$e->getMessage()];
        }

        if ($response->successful() && $response->json('available_balance') !== null) {
            return [
                'ok' => true,
                'message' => 'Junipay accepted these keys. Available balance: GH₵'.number_format((float) $response->json('available_balance'), 2).'.',
            ];
        }

        return ['ok' => false, 'message' => $this->errorMessage($response) ?: 'Junipay rejected these keys.'];
    }

    public function rechargeQuote(float $creditGhs, string $method = 'momo'): array
    {
        return app(PaystackService::class)->rechargeQuote($creditGhs, $method);
    }

    public function topUpCreditFromMetadata(array $metadata, float $paidGhs): float
    {
        return app(PaystackService::class)->topUpCreditFromMetadata($metadata, $paidGhs);
    }

    public function amountsMatch(float $paidGhs, float $expectedGhs, float $tolerance = 0.01): bool
    {
        return abs(round($paidGhs, 2) - round($expectedGhs, 2)) <= $tolerance;
    }

    public function initializePayment(
        string $email,
        float $amountGhs,
        string $reference,
        string $customerName,
        array $meta = [],
        ?string $redirectUrl = null,
        string $title = 'CityShop',
        ?string $customerPhone = null,
    ): array {
        if (! $this->isAvailable()) {
            throw new \RuntimeException($this->unavailableMessage());
        }

        $amount = round(max(0, $amountGhs), 2);
        if ($amount < 1) {
            throw new \RuntimeException('Amount is too small to start payment.');
        }

        $userId = (int) ($meta['user_id'] ?? $meta['buyer_id'] ?? 0);
        JunipayPayment::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $userId > 0 ? $userId : null,
                'amount' => $amount,
                'meta' => array_merge($meta, ['gateway' => 'junipay', 'title' => $title, 'email' => $email]),
                'status' => 'pending',
                'redirect_url' => $redirectUrl ?: url('/api/v1/flutterwave/mobile-return'),
                'phone' => $this->localPhone((string) $customerPhone),
                'trans_id' => null,
                'message' => null,
            ],
        );

        return [
            'authorization_url' => URL::temporarySignedRoute('junipay.pay', now()->addHours(2), ['reference' => $reference]),
            'reference' => $reference,
            'email' => $email,
        ];
    }

    public function initializeWalletTopUp(
        User $user,
        float $creditGhs,
        string $method,
        string $callbackUrl,
        ?string $referencePrefix = null,
        array $extraMetadata = [],
    ): array {
        $quote = $this->rechargeQuote($creditGhs, $method);
        $reference = PaymentReference::recharge();
        $email = $user->billingEmail();

        $data = $this->initializePayment(
            $email,
            $quote['charge'],
            $reference,
            (string) $user->name,
            array_merge([
                'type' => 'wallet_topup',
                'user_id' => $user->id,
                'method' => $method,
                'wallet_credit' => $quote['credit'],
                'paystack_fee' => $quote['fee'],
                'gateway_fee' => $quote['fee'],
                'expected_amount' => $quote['charge'],
            ], $extraMetadata),
            $callbackUrl,
            'CityShop wallet top-up',
            (string) $user->mobile,
        );

        return [
            'authorization_url' => $data['authorization_url'],
            'reference' => $data['reference'],
            'email' => $email,
            'credit' => $quote['credit'],
            'fee' => $quote['fee'],
            'charge' => $quote['charge'],
        ];
    }

    /**
     * Send the Mobile Money prompt for a stored payment.
     */
    public function sendPrompt(JunipayPayment $payment, string $phone, string $network): JunipayPayment
    {
        if ($payment->status === 'success') {
            return $payment;
        }

        $phone = $this->localPhone($phone);
        $provider = $this->providerFor($network);
        if ($phone === '' || $provider === null) {
            throw new \InvalidArgumentException('Enter a valid MTN, Telecel or AirtelTigo Mobile Money number.');
        }

        if (filled($payment->trans_id) && $payment->status === 'pending' && $payment->updated_at?->gt(now()->subSeconds(60))) {
            throw new \InvalidArgumentException('A prompt was just sent. Wait a minute before sending another one.');
        }

        $meta = $payment->meta ?? [];
        if (filled($payment->trans_id)) {
            $meta['previous_trans_ids'] = array_values(array_unique([...($meta['previous_trans_ids'] ?? []), (string) $payment->trans_id]));
        }
        $response = $this->client()->post('/payment', [
            'senderEmail' => (string) ($meta['email'] ?? ''),
            'phoneNumber' => $phone,
            'channel' => 'mobile_money',
            'provider' => $provider,
            'amount' => (float) $payment->amount,
            'tot_amnt' => (float) $payment->amount,
            'description' => mb_substr((string) ($meta['title'] ?? 'CityShop payment'), 0, 60),
            'foreignID' => self::foreignId($payment->reference),
            'callbackUrl' => url('/webhooks/junipay'),
        ]);

        $status = $this->bodyStatus($response);
        $transId = $this->transIdFrom($response);
        if (! $this->accepted($response) || in_array($status, ['failed', 'error'], true) || $transId === '') {
            Log::warning('Junipay collection failed to start', [
                'reference' => $payment->reference,
                'http' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException($this->errorMessage($response) ?: 'Junipay could not send the payment prompt. Try again.');
        }

        $payment->update([
            'meta' => $meta,
            'phone' => $phone,
            'provider' => $provider,
            'trans_id' => $transId,
            'status' => in_array($status, ['success', 'successful', 'completed'], true) ? 'success' : 'pending',
            'message' => null,
        ]);

        return $payment->fresh() ?? $payment;
    }

    /**
     * Ask Junipay for the status of a transaction it gave us.
     *
     * @return string success, failed or pending
     */
    /**
     * Raw /checktranstatus reply, for support commands only.
     *
     * @return array{http: int, body: mixed}
     */
    public function rawStatus(string $transId): array
    {
        $response = $this->client()->post('/checktranstatus', ['transID' => $transId]);

        return ['http' => $response->status(), 'body' => $response->json() ?? $response->body()];
    }

    public function transactionStatus(string $transId): string
    {
        return $this->statusReport($transId)['status'];
    }

    /**
     * Status plus any later transaction id Junipay nested in the reply.
     * A finished MoMo payout can say SUCCESS under info while the outer status is still pending.
     *
     * @return array{status: string, trans_id: string}
     */
    public function statusReport(string $transId): array
    {
        $response = $this->client()->post('/checktranstatus', ['transID' => $transId]);
        if (! $this->accepted($response)) {
            throw new \RuntimeException($this->errorMessage($response) ?: 'Junipay status check failed.');
        }

        $info = $response->json('info');
        if (is_string($info) && str_contains(strtolower($info), 'not found')) {
            throw new \RuntimeException('Junipay has no record of this transaction.');
        }

        $status = $this->classifyStatus($response);
        if ($status === '') {
            throw new \RuntimeException($this->errorMessage($response) ?: 'Junipay status check failed.');
        }

        $later = '';
        foreach (['info.transID', 'info.trans_id', 'transID', 'trans_id'] as $path) {
            $value = $response->json($path);
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '' && $value !== $transId) {
                $later = $value;
                break;
            }
        }

        return ['status' => $status, 'trans_id' => $later];
    }

    private function classifyStatus(Response $response): string
    {
        $values = [];
        foreach (['status', 'info.status', 'info.message', 'info.description', 'message', 'description', 'data.status'] as $path) {
            $value = $response->json($path);
            if (is_string($value) && trim($value) !== '') {
                $values[] = strtolower(trim($value));
            }
        }

        foreach ($values as $value) {
            if ($this->isFailedStatus($value)) {
                return 'failed';
            }
        }
        foreach ($values as $value) {
            if ($this->isSuccessStatus($value)) {
                return 'success';
            }
        }

        return $values === [] ? '' : 'pending';
    }

    private function isFailedStatus(string $value): bool
    {
        return in_array($value, ['failed', 'failure', 'declined', 'cancelled', 'canceled', 'reversed', 'transaction failed'], true)
            || str_contains($value, 'fail')
            || str_contains($value, 'declin');
    }

    private function isSuccessStatus(string $value): bool
    {
        if (str_contains($value, 'fail') || str_contains($value, 'pending') || str_contains($value, 'unsuccess')) {
            return false;
        }

        return in_array($value, [
            'success', 'successful', 'completed', 'complete', 'paid', 'approved',
            'transaction successful', 'transfer successful', 'payment successful',
        ], true) || str_contains($value, 'success');
    }

    /**
     * Refresh a collection from Junipay and return it.
     */
    public function refresh(JunipayPayment $payment): JunipayPayment
    {
        if ($payment->status === 'success' || ! filled($payment->trans_id)) {
            return $payment;
        }

        try {
            $status = $this->transactionStatus((string) $payment->trans_id);
        } catch (\Throwable) {
            $status = 'pending';
        }
        if ($status !== 'success') {
            foreach ($payment->meta['previous_trans_ids'] ?? [] as $earlier) {
                try {
                    if ($this->transactionStatus((string) $earlier) === 'success') {
                        $status = 'success';
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if ($status !== $payment->status) {
            $payment->update(['status' => $status]);
        }

        return $payment;
    }

    /** True when Junipay gave us this transaction ID for the payment, now or on an earlier prompt. */
    public function ownsTransaction(JunipayPayment $payment, string $transId): bool
    {
        if ($transId === '') {
            return false;
        }

        $known = [(string) $payment->trans_id, ...array_map('strval', $payment->meta['previous_trans_ids'] ?? [])];

        return in_array($transId, array_filter($known), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyByReference(string $reference): array
    {
        $reference = trim($reference);
        $payment = $reference !== '' ? JunipayPayment::query()->where('reference', $reference)->first() : null;
        if (! $payment) {
            throw new \RuntimeException('Payment not found.');
        }

        $payment = $this->refresh($payment);

        return [
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => 'GHS',
            'reference' => $payment->reference,
            'tx_ref' => $payment->reference,
            'trans_id' => $payment->trans_id,
            'meta' => $payment->meta ?? [],
        ];
    }

    public function paidAmountGhs(array $data): float
    {
        return round((float) ($data['amount'] ?? 0), 2);
    }

    public function isSuccessful(array $data): bool
    {
        return strtolower((string) ($data['status'] ?? '')) === 'success';
    }

    public function normalizeMeta(array $data): array
    {
        $meta = $data['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    /**
     * Pay out to Mobile Money or a bank account.
     *
     * @return array{status: string, trans_id: string, message: string}
     */
    public function transfer(
        string $reference,
        float $amountGhs,
        string $network,
        string $accountNumber,
        string $receiverName,
        string $narration,
        ?string $receiverPhone = null,
    ): array {
        $payload = [
            'amount' => round($amountGhs, 2),
            'foreignID' => $reference,
            'sender' => 'CITY UNLOCK VENTURES',
            'receiver' => $receiverName !== '' ? $receiverName : 'CityShop customer',
            'narration' => self::narration($narration),
            'callbackUrl' => url('/webhooks/junipay'),
        ];

        if (GhanaBanks::isBank($network)) {
            $bankCode = self::BANK_CODES[$network] ?? null;
            if ($bankCode === null) {
                throw new \RuntimeException('Junipay cannot pay '.GhanaBanks::label($network).'. Pay this one manually.');
            }
            $payload += [
                'channel' => 'bank',
                'bank_code' => $bankCode,
                'account_number' => preg_replace('/\D+/', '', $accountNumber) ?: $accountNumber,
            ];
            $phone = $this->localPhone((string) $receiverPhone);
            if ($phone !== '') {
                $payload['receiver_phone'] = $phone;
            }
        } else {
            $provider = $this->providerFor($network);
            $phone = $this->localPhone($accountNumber);
            if ($provider === null || $phone === '') {
                throw new \RuntimeException('This withdrawal has no valid MTN, Telecel or AirtelTigo number.');
            }
            $payload += [
                'channel' => 'mobile_money',
                'provider' => $provider,
                'phoneNumber' => $phone,
                'receiver_phone' => $phone,
            ];
        }

        $response = $this->client()->post('/transfer', $payload);
        $status = $this->bodyStatus($response);
        $transId = $this->transIdFrom($response);

        if (! $this->accepted($response) || $transId === '' || in_array($status, ['failed', 'error'], true)) {
            Log::warning('Junipay transfer failed to start', [
                'reference' => $reference,
                'http' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException($this->errorMessage($response) ?: 'Junipay could not start this payout.');
        }

        return [
            'status' => in_array($status, ['success', 'successful', 'completed'], true) ? 'success' : 'pending',
            'trans_id' => $transId,
            'message' => $this->errorMessage($response),
        ];
    }

    /** Junipay rejects narration that contains anything except letters, numbers and spaces. */
    public static function narration(string $narration): string
    {
        $clean = trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $narration));
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));

        return mb_substr($clean !== '' ? $clean : 'CityShop withdrawal', 0, 40);
    }

    /**
     * Junipay bank payouts only accept foreignID matching /^\d+$/.
     */
    public static function payoutReference(int $id): string
    {
        $suffix = '';
        for ($i = 0; $i < 7; $i++) {
            $suffix .= (string) random_int(0, 9);
        }

        return '9'.str_pad((string) max(1, $id), 8, '0', STR_PAD_LEFT).$suffix;
    }

    /** Junipay requires foreignID to be at least 13 characters; our references are 12. */
    public static function foreignId(string $reference): string
    {
        return self::FOREIGN_PREFIX.$reference;
    }

    public static function referenceFromForeignId(string $foreignId): string
    {
        return str_starts_with($foreignId, self::FOREIGN_PREFIX)
            ? substr($foreignId, strlen(self::FOREIGN_PREFIX))
            : $foreignId;
    }

    /** Junipay wants the local 10-digit format, e.g. 0244444444. */
    public function localPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        } elseif (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        return preg_match('/^0\d{9}$/', $digits) ? $digits : '';
    }

    public function providerFor(?string $network): ?string
    {
        return match (strtolower((string) $network)) {
            'mtn' => 'mtn',
            'telecel', 'vodafone' => 'vodafone',
            'airteltigo', 'at', 'airtel', 'tigo' => 'airteltigo',
            default => null,
        };
    }

    /** Junipay answers HTTP 200 for most errors and puts the real code in the body. */
    private function accepted(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }
        $code = $response->json('code');

        return $code === null || ! is_numeric($code) || ((int) $code >= 200 && (int) $code < 300);
    }

    private function bodyStatus(Response $response): string
    {
        foreach (['status', 'info.status', 'data.status', 'transaction.status'] as $path) {
            $value = $response->json($path);
            if (is_string($value) && trim($value) !== '') {
                return strtolower(trim($value));
            }
        }

        return '';
    }

    private function transIdFrom(Response $response): string
    {
        foreach (['transID', 'trans_id', 'transId', 'info.transID', 'info.trans_id', 'data.transID', 'data.trans_id', 'transaction.transID'] as $path) {
            $value = $response->json($path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function errorMessage(Response $response): string
    {
        foreach (['message', 'info.message', 'data.message', 'error'] as $path) {
            $value = $response->json($path);
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)));
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '' && ! in_array(strtolower($value), ['success', 'successful'], true)) {
                return $value;
            }
        }

        return match ((int) ($response->json('code') ?? $response->status())) {
            401 => 'Junipay rejected the Client ID or private key.',
            403 => 'Junipay has not approved Direct API access for this account yet.',
            default => '',
        };
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
