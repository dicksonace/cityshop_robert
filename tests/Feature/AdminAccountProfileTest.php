<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_seller_name_email_and_phone(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'name' => 'Old Seller',
            'email' => 'old.seller@example.com',
            'mobile' => '0241112223',
        ]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Test Store',
            'status' => SellerStatus::Approved,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.sellers.update-profile', $profile), [
                'name' => 'Asare Kwame',
                'email' => 'eskimlitcenter1@gmail.com',
                'mobile' => '0532700209',
                'ghana_card_number' => 'GHA123445677',
                'region' => 'Western North',
                'city' => 'Sefwi Bekwai',
                'residential_address' => 'Sefwi Bekwai HFC',
                'store_name' => 'City Unlock',
                'is_business_registered' => false,
                'accept_marketplace_payments' => true,
                'accept_direct_payments' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $seller->refresh();
        $this->assertSame('Asare Kwame', $seller->name);
        $this->assertSame('eskimlitcenter1@gmail.com', $seller->email);
        $this->assertSame('0532700209', $seller->mobile);
        $this->assertSame('GHA123445677', $seller->ghana_card_number);
        $this->assertSame('Western North', $seller->region);
        $this->assertSame('Sefwi Bekwai', $seller->city);
        $this->assertSame('Sefwi Bekwai HFC', $seller->residential_address);

        $profile->refresh();
        $this->assertSame('City Unlock', $profile->store_name);
        $this->assertFalse($profile->is_business_registered);
        $this->assertTrue($profile->accept_marketplace_payments);
        $this->assertTrue($profile->accept_direct_payments);
    }

    public function test_admin_can_update_buyer_name_email_and_phone(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi amoah',
            'email' => 'kofi@example.com',
            'mobile' => '0249998887',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.buyers.update', $buyer), [
                'name' => 'Kofi Amoah',
                'email' => 'kofi.amoah@example.com',
                'mobile' => '0550001112',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $buyer->refresh();
        $this->assertSame('Kofi Amoah', $buyer->name);
        $this->assertSame('kofi.amoah@example.com', $buyer->email);
        $this->assertSame('0550001112', $buyer->mobile);
    }

    public function test_buyer_cannot_update_another_buyer_via_admin_route(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($other)
            ->patch(route('admin.buyers.update', $buyer), [
                'name' => 'Hacked',
                'email' => 'hacked@example.com',
                'mobile' => '0200000000',
            ])
            ->assertForbidden();
    }

    public function test_api_profile_update_stays_locked(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        Sanctum::actingAs($seller);

        $this->patchJson('/api/v1/profile', [
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
            'mobile' => '0200000000',
        ])->assertForbidden();
    }
}
