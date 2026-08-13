<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PaystackService;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackFeeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_percent_fee_is_added_to_the_charge(): void
    {
        $quote = app(PaystackService::class)->rechargeQuote(100);

        $this->assertTrue($quote['fee'] > 0);
        $this->assertEqualsWithDelta(100 + $quote['fee'], $quote['charge'], 0.01);
        $this->assertSame('percent', $quote['mode']);
    }

    public function test_flat_fee_adds_one_amount(): void
    {
        PlatformSettings::savePaystackFeeSettings([
            'enabled' => true,
            'mode' => 'flat',
            'percent' => 1.95,
            'flat' => 1,
            'tiers' => PlatformSettings::defaultPaystackFeeTiers(),
        ]);

        $quote = app(PaystackService::class)->rechargeQuote(50);

        $this->assertSame(1.0, $quote['fee']);
        $this->assertSame(51.0, $quote['charge']);
        $this->assertSame(50.0, $quote['credit']);
    }

    public function test_range_fee_uses_the_matching_band(): void
    {
        PlatformSettings::savePaystackFeeSettings([
            'enabled' => true,
            'mode' => 'tiers',
            'percent' => 1.95,
            'flat' => 0,
            'tiers' => [
                ['min' => 1, 'max' => 99.99, 'fee' => 1],
                ['min' => 100, 'max' => 999.99, 'fee' => 2],
                ['min' => 1000, 'max' => null, 'fee' => 5],
            ],
        ]);

        $this->assertSame(1.0, app(PaystackService::class)->rechargeQuote(20)['fee']);
        $this->assertSame(2.0, app(PaystackService::class)->rechargeQuote(150)['fee']);
        $this->assertSame(5.0, app(PaystackService::class)->rechargeQuote(2500)['fee']);
    }

    public function test_disabled_fees_charge_the_net_amount_only(): void
    {
        PlatformSettings::savePaystackFeeSettings([
            'enabled' => false,
            'mode' => 'flat',
            'percent' => 1.95,
            'flat' => 10,
            'tiers' => [],
        ]);

        $quote = app(PaystackService::class)->rechargeQuote(80);

        $this->assertSame(0.0, $quote['fee']);
        $this->assertSame(80.0, $quote['charge']);
    }

    public function test_admin_can_save_paystack_fees(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.paystack-fees.settings.update'), [
                'enabled' => true,
                'mode' => 'flat',
                'percent' => 1.95,
                'flat' => 1.5,
                'tiers' => [
                    ['min' => 1, 'max' => 100, 'fee' => 1],
                    ['min' => 100.01, 'max' => null, 'fee' => 3],
                ],
            ])
            ->assertRedirect();

        $saved = PlatformSettings::paystackFeeSettings();
        $this->assertTrue($saved['enabled']);
        $this->assertSame('flat', $saved['mode']);
        $this->assertSame(1.5, $saved['flat']);
    }

    public function test_paid_covers_checkout_with_or_without_fee(): void
    {
        PlatformSettings::savePaystackFeeSettings([
            'enabled' => true,
            'mode' => 'flat',
            'percent' => 0,
            'flat' => 1,
            'tiers' => [],
        ]);

        $paystack = app(PaystackService::class);

        $this->assertTrue($paystack->paidCoversCheckout(5.0, 5.0));
        $this->assertTrue($paystack->paidCoversCheckout(6.0, 5.0));
        $this->assertFalse($paystack->paidCoversCheckout(4.5, 5.0));
    }
}
