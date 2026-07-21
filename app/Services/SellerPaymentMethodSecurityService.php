<?php

namespace App\Services;

use App\Models\SellerPaymentMethod;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SellerPaymentMethodSecurityService
{
    public function disable(SellerPaymentMethod $method, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to disable a payment method.');
        }

        if ($method->isDisabled()) {
            throw new InvalidArgumentException('This payment method is already disabled.');
        }

        DB::transaction(function () use ($method, $admin, $reason) {
            $method->update([
                'is_active' => false,
                'is_default' => false,
                'disabled_at' => now(),
                'disabled_by' => $admin->id,
                'disabled_reason' => $reason,
            ]);

            $profile = $method->sellerProfile()->lockForUpdate()->firstOrFail();

            $this->lockPaymentSetup(
                $profile,
                $admin,
                'Payment method disabled as suspicious: '.$reason,
            );

            if ($profile->paymentMethods()->where('is_active', true)->count() === 0) {
                $profile->update(['accept_direct_payments' => false]);
            }

            $this->ensureDefaultMethod($profile);
        });
    }

    public function enable(SellerPaymentMethod $method, User $admin): void
    {
        if (! $method->isDisabled()) {
            throw new InvalidArgumentException('This payment method is not disabled.');
        }

        DB::transaction(function () use ($method, $admin) {
            $method->update([
                'is_active' => true,
                'disabled_at' => null,
                'disabled_by' => null,
                'disabled_reason' => null,
            ]);

            $profile = $method->sellerProfile()->lockForUpdate()->firstOrFail();

            $stillDisabled = $profile->paymentMethods()
                ->whereNotNull('disabled_at')
                ->exists();

            if (! $stillDisabled) {
                $this->unlockPaymentSetup($profile);
            }

            $this->ensureDefaultMethod($profile);
        });
    }

    public function lockPaymentSetup(SellerProfile $profile, User $admin, string $reason): void
    {
        $profile->update([
            'payment_methods_locked_at' => $profile->payment_methods_locked_at ?? now(),
            'payment_methods_locked_by' => $admin->id,
            'payment_methods_lock_reason' => $reason,
        ]);
    }

    public function unlockPaymentSetup(SellerProfile $profile): void
    {
        $profile->update([
            'payment_methods_locked_at' => null,
            'payment_methods_locked_by' => null,
            'payment_methods_lock_reason' => null,
        ]);
    }

    public function assertCanManagePaymentMethods(SellerProfile $profile): void
    {
        if ($profile->paymentMethodsAreLocked()) {
            throw new InvalidArgumentException(
                $profile->payment_methods_lock_reason
                    ?: 'Your payment methods are locked by CityShop admin. Contact support.'
            );
        }
    }

    public function assertAccountNotBlocked(SellerProfile $profile, ?string $accountNumber): void
    {
        if (! $accountNumber) {
            return;
        }

        $normalized = $this->normalizeAccount($accountNumber);
        if ($normalized === '') {
            return;
        }

        $blocked = $profile->paymentMethods()
            ->whereNotNull('disabled_at')
            ->get()
            ->contains(fn (SellerPaymentMethod $method) => $this->normalizeAccount($method->account_number) === $normalized);

        if ($blocked) {
            throw new InvalidArgumentException(
                'This account number was disabled by admin and cannot be added again.'
            );
        }
    }

    public function normalizeAccount(?string $accountNumber): string
    {
        return preg_replace('/\D+/', '', $accountNumber ?? '') ?? '';
    }

    private function ensureDefaultMethod(SellerProfile $profile): void
    {
        if ($profile->paymentMethods()->where('is_active', true)->where('is_default', true)->exists()) {
            return;
        }

        $profile->paymentMethods()
            ->where('is_active', true)
            ->latest('id')
            ->first()
            ?->update(['is_default' => true]);
    }
}
