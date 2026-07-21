<?php

namespace Tests\Feature;

use App\Enums\SellerPaymentMethodType;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\SellerPaymentMethod;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPaymentMethodSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function approvedSeller(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Secure Pay Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_direct_payments' => true,
            'accept_marketplace_payments' => true,
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        $method = SellerPaymentMethod::create([
            'seller_profile_id' => $profile->id,
            'type' => SellerPaymentMethodType::MobileMoney,
            'account_name' => 'Test Seller',
            'account_number' => '0244123456',
            'network' => 'MTN',
            'is_active' => true,
            'is_default' => true,
        ]);

        return compact('seller', 'profile', 'method');
    }

    public function test_admin_can_disable_payment_method_and_lock_setup(): void
    {
        ['seller' => $seller, 'profile' => $profile, 'method' => $method] = $this->approvedSeller();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.disable', [$profile, $method]), [
                'reason' => 'Suspicious MoMo ownership mismatch',
            ])
            ->assertRedirect();

        $method->refresh();
        $profile->refresh();

        $this->assertFalse($method->is_active);
        $this->assertNotNull($method->disabled_at);
        $this->assertSame('Suspicious MoMo ownership mismatch', $method->disabled_reason);
        $this->assertTrue($profile->paymentMethodsAreLocked());
        $this->assertFalse($profile->accept_direct_payments);

        $this->actingAs($seller)
            ->post(route('seller.payment-methods.store'), [
                'type' => 'mobile_money',
                'account_name' => 'Another Account',
                'account_number' => '0555123456',
                'network' => 'MTN',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, $profile->paymentMethods()->count());
    }

    public function test_admin_can_enable_method_and_clear_lock_when_none_remain_disabled(): void
    {
        ['profile' => $profile, 'method' => $method] = $this->approvedSeller();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.disable', [$profile, $method]), [
                'reason' => 'Under review for fraud',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.enable', [$profile, $method]))
            ->assertRedirect();

        $method->refresh();
        $profile->refresh();

        $this->assertTrue($method->is_active);
        $this->assertNull($method->disabled_at);
        $this->assertFalse($profile->paymentMethodsAreLocked());
    }

    public function test_seller_cannot_delete_disabled_payment_method(): void
    {
        ['seller' => $seller, 'profile' => $profile, 'method' => $method] = $this->approvedSeller();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.disable', [$profile, $method]), [
                'reason' => 'Flagged as suspicious account',
            ]);

        $this->actingAs($seller)
            ->delete(route('seller.payment-methods.destroy', $method->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('seller_payment_methods', [
            'id' => $method->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_unlock_allows_new_methods_but_keeps_disabled_ones_blocked(): void
    {
        ['seller' => $seller, 'profile' => $profile, 'method' => $method] = $this->approvedSeller();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.disable', [$profile, $method]), [
                'reason' => 'Suspicious activity reported',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.payment-methods.unlock', $profile))
            ->assertRedirect();

        $profile->refresh();
        $this->assertFalse($profile->paymentMethodsAreLocked());
        $this->assertTrue($method->fresh()->isDisabled());

        $this->actingAs($seller)
            ->post(route('seller.payment-methods.store'), [
                'type' => 'mobile_money',
                'account_name' => 'Clean Account',
                'account_number' => '0200111222',
                'network' => 'Telecel',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($seller)
            ->post(route('seller.payment-methods.store'), [
                'type' => 'mobile_money',
                'account_name' => 'Same Bad Number',
                'account_number' => '0244-123-456',
                'network' => 'MTN',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
