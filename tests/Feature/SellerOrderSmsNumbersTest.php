<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderSmsNumbersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seller_can_save_and_change_two_order_sms_numbers(): void
    {
        [$seller] = $this->approvedSeller();

        $this->actingAs($seller)
            ->post(route('seller.account.order-sms'), [
                'order_sms_mobile_1' => '0244111000',
                'order_sms_mobile_2' => '0204111001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $seller->id,
            'order_sms_mobile_1' => '0244111000',
            'order_sms_mobile_2' => '0204111001',
        ]);

        $this->actingAs($seller)
            ->post(route('seller.account.order-sms'), [
                'order_sms_mobile_1' => '0244111999',
                'order_sms_mobile_2' => '',
            ])
            ->assertRedirect();

        $seller->sellerProfile->refresh();
        $this->assertSame('0244111999', $seller->sellerProfile->order_sms_mobile_1);
        $this->assertNull($seller->sellerProfile->order_sms_mobile_2);
    }

    public function test_new_order_sms_goes_to_both_saved_numbers(): void
    {
        [$seller] = $this->approvedSeller('0241000000');
        $seller->sellerProfile->update([
            'order_sms_mobile_1' => '0244111000',
            'order_sms_mobile_2' => '0204111001',
        ]);

        $item = new OrderItem(['product_name' => 'HP 1040G8 i5']);
        $order = new Order([
            'order_number' => 'CS20260814001',
            'payment_status' => PaymentStatus::Paid,
        ]);
        $notification = new PaymentConfirmedNotification($order, $item);

        $this->assertSame(
            ['0244111000', '0204111001'],
            $notification->smsRecipients($seller->fresh('sellerProfile')),
        );
        $this->assertContains(SmsChannel::class, $notification->via($seller->fresh('sellerProfile')));
    }

    public function test_new_order_sms_falls_back_to_account_mobile(): void
    {
        [$seller] = $this->approvedSeller('0241000000');
        $item = new OrderItem(['product_name' => 'Cable']);
        $order = new Order([
            'order_number' => 'CS20260814002',
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->assertSame(
            ['0241000000'],
            (new PaymentConfirmedNotification($order, $item))->smsRecipients($seller->fresh('sellerProfile')),
        );
    }

    /**
     * @return array{0: User, 1: SellerProfile}
     */
    private function approvedSeller(string $mobile = '0244111222'): array
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'mobile' => $mobile,
        ]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'SMS Store',
            'store_name' => 'SMS Store',
            'slug' => 'sms-store-'.uniqid(),
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_marketplace_payments' => true,
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        return [$seller->fresh('sellerProfile'), $profile];
    }
}
