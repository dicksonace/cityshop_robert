<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentPinService;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BankWithdrawalFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_fee_is_twenty_from_one_thousand(): void
    {
        $this->assertSame(10.0, PlatformSettings::feeForPayoutType('bank', 999));
        $this->assertSame(20.0, PlatformSettings::feeForPayoutType('bank', 1000));
        $this->assertSame(20.0, PlatformSettings::feeForPayoutType('bank', 1500));
        $this->assertSame(20.0, PlatformSettings::feeForPayoutType('bank', 5000));
        $this->assertSame(0.0, PlatformSettings::feeForPayoutType('momo', 1500));
    }

    public function test_old_single_ten_cedi_band_upgrades_from_one_thousand(): void
    {
        $this->putRawBankFeeTiers([
            ['min' => 10, 'max' => 1000, 'fee' => 10],
        ]);

        $this->assertSame(10.0, PlatformSettings::feeForPayoutType('bank', 500));
        $this->assertSame(20.0, PlatformSettings::feeForPayoutType('bank', 1000));
        $this->assertSame(20.0, PlatformSettings::feeForPayoutType('bank', 1500));
    }

    public function test_seller_web_bank_withdrawal_from_one_thousand_charges_twenty(): void
    {
        $seller = $this->approvedSeller();
        PaymentPinService::set($seller, '2468');
        Wallet::create([
            'user_id' => $seller->id,
            'available_balance' => 2149.10,
            'pending_balance' => 0,
            'total_earnings' => 2149.10,
            'withdrawn_amount' => 0,
        ]);

        $this->putRawBankFeeTiers([
            ['min' => 10, 'max' => 1000, 'fee' => 10],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.wallet.withdraw'), [
                'amount' => 1500,
                'payout_type' => 'bank',
                'momo_number' => '22558089',
                'account_name' => 'Robert Asare',
                'network' => 'advans',
                'payment_pin' => '2468',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $seller->id,
            'amount' => 1500,
            'payout_channel' => 'bank',
            'fee' => 20,
            'status' => WithdrawalStatus::Pending->value,
        ]);

        $this->assertEquals(629.10, (float) Wallet::where('user_id', $seller->id)->value('available_balance'));
    }

    /** @param  list<array{min: float|int, max: float|int|null, fee: float|int}>  $tiers */
    private function putRawBankFeeTiers(array $tiers): void
    {
        PlatformSetting::updateOrCreate(
            ['key' => PlatformSettings::WITHDRAWAL_FEE_KEY],
            ['value' => json_encode([
                'enabled' => true,
                'amount' => 10,
                'applies_to' => 'bank',
                'bank_tiers' => $tiers,
            ])],
        );
        Cache::forget('platform_setting.'.PlatformSettings::WITHDRAWAL_FEE_KEY);
    }

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Fee Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        return $seller->fresh();
    }
}
