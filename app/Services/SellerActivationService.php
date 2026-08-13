<?php

namespace App\Services;

use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\SellerActivationDueNotification;
use App\Notifications\SellerActivationPaidNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerActivationService
{
    public const DURATION = '1 year';

    public function prompt(SellerProfile $seller, float $amount): void
    {
        if (! $seller->isApproved()) {
            throw ValidationException::withMessages([
                'amount' => ['Only approved sellers can be asked to pay the service fee.'],
            ]);
        }

        $amount = round($amount, 2);
        if ($amount < 1 || $amount > 50000) {
            throw ValidationException::withMessages([
                'amount' => ['Enter an amount between GH₵1 and GH₵50,000.'],
            ]);
        }

        $seller->update([
            'activation_fee_amount' => $amount,
            'activation_prompted_at' => now('Africa/Accra'),
        ]);

        $seller->loadMissing('user');
        $seller->user->notify(new SellerActivationDueNotification($seller->fresh()));

        AppNotificationService::send(
            $seller->user,
            'seller_activation_due',
            'Pay seller service fee',
            'Pay GH₵'.number_format($amount, 2).' for 1 year to keep your store live. Buyers cannot see your products until you pay. You can still withdraw and recharge.',
            ['href' => route('seller.activation.show')],
        );
    }

    public function waiveForYear(SellerProfile $seller, ?float $amount = null): void
    {
        if (! $seller->isApproved()) {
            throw ValidationException::withMessages([
                'amount' => ['Only approved sellers can be activated.'],
            ]);
        }

        $fee = $amount !== null && $amount > 0
            ? round($amount, 2)
            : (float) ($seller->activation_fee_amount ?? 0);

        $seller->update([
            'activation_fee_amount' => $fee > 0 ? $fee : $seller->activation_fee_amount,
            'activation_prompted_at' => $seller->activation_prompted_at ?? now('Africa/Accra'),
            'activation_paid_at' => now('Africa/Accra'),
            'activation_paid_until' => now('Africa/Accra')->addYear(),
        ]);
    }

    public function endNow(SellerProfile $seller): void
    {
        if ((float) ($seller->activation_fee_amount ?? 0) < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Set a service fee amount first, then prompt the seller.'],
            ]);
        }

        $seller->update([
            'activation_prompted_at' => $seller->activation_prompted_at ?? now('Africa/Accra'),
            'activation_paid_until' => now('Africa/Accra')->subMinute(),
        ]);

        $seller->loadMissing('user');
        $seller->user->notify(new SellerActivationDueNotification($seller->fresh()));
    }

    public function payFromWallet(User $seller, ?string $pin): SellerProfile
    {
        PaymentPinService::assertValidForAction($seller, $pin);

        $profile = $seller->sellerProfile;
        if (! $profile || ! $profile->isApproved()) {
            throw ValidationException::withMessages([
                'amount' => ['Your seller account cannot pay activation right now.'],
            ]);
        }
        if (! $profile->needsActivationPayment()) {
            throw ValidationException::withMessages([
                'amount' => ['Your store is already active.'],
            ]);
        }

        $amount = round((float) $profile->activation_fee_amount, 2);
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['No service fee has been set. Contact CityShop admin.'],
            ]);
        }

        $until = now('Africa/Accra')->addYear();

        DB::transaction(function () use ($seller, $profile, $amount, $until) {
            WalletService::debitAvailable(
                $seller,
                $amount,
                'Insufficient wallet balance. Recharge first, then pay the service fee. You can still withdraw.',
            );

            $profile->update([
                'activation_paid_at' => now('Africa/Accra'),
                'activation_paid_until' => $until,
            ]);

            WalletTransactionService::recordServiceFee($seller->id, $amount, $until);
        });

        $fresh = $profile->fresh();
        $balance = (float) (Wallet::query()->where('user_id', $seller->id)->value('available_balance') ?? 0);
        $seller->notify(new SellerActivationPaidNotification($fresh, $balance));

        AppNotificationService::send(
            $seller,
            'seller_activation_paid',
            'Seller service fee paid',
            'Your store is active until '.$until->timezone('Africa/Accra')->format('j M Y').'.',
            ['href' => route('seller.dashboard')],
        );

        return $fresh;
    }
}
