<?php

namespace App\Contracts;

use App\Models\User;

/**
 * A gateway that sends the customer to a hosted payment page (Flutterwave, Korapay).
 */
interface HostedPaymentGateway
{
    public function name(): string;

    public function isAvailable(): bool;

    public function unavailableMessage(): string;

    /**
     * @return array{credit: float, fee: float, charge: float, percent: float, flat: float, mode: string}
     */
    public function rechargeQuote(float $creditGhs, string $method = 'momo'): array;

    public function topUpCreditFromMetadata(array $metadata, float $paidGhs): float;

    public function amountsMatch(float $paidGhs, float $expectedGhs, float $tolerance = 0.01): bool;

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: string, reference: string, email: string}
     */
    public function initializePayment(
        string $email,
        float $amountGhs,
        string $reference,
        string $customerName,
        array $meta = [],
        ?string $redirectUrl = null,
        string $title = 'CityShop',
        ?string $customerPhone = null,
    ): array;

    /**
     * @param  array<string, mixed>  $extraMetadata
     * @return array{authorization_url: string, reference: string, email: string, credit: float, fee: float, charge: float}
     */
    public function initializeWalletTopUp(
        User $user,
        float $creditGhs,
        string $method,
        string $callbackUrl,
        ?string $referencePrefix = null,
        array $extraMetadata = [],
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function verifyByReference(string $reference): array;

    public function paidAmountGhs(array $data): float;

    public function isSuccessful(array $data): bool;

    /**
     * @return array<string, mixed>
     */
    public function normalizeMeta(array $data): array;
}
