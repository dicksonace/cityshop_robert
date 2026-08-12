<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPaymentMethodBankSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_must_pick_a_known_bank_when_adding_a_bank_method(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($seller)
            ->post(route('seller.payment-methods.store'), [
                'type' => 'bank',
                'account_name' => 'Robert Asare',
                'account_number' => '22558089',
                'bank_name' => 'My Own Bank',
            ])
            ->assertSessionHasErrors('bank_name');
    }

    public function test_seller_bank_method_saves_the_selected_bank_label(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($seller)
            ->post(route('seller.payment-methods.store'), [
                'type' => 'bank',
                'account_name' => 'Robert Asare',
                'account_number' => '22558089',
                'bank_name' => 'gcb',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('seller_payment_methods', [
            'seller_profile_id' => $seller->sellerProfile->id,
            'type' => 'bank',
            'bank_name' => 'GCB',
            'account_number' => '22558089',
        ]);
    }

    public function test_payment_methods_page_includes_the_bank_list(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($seller)
            ->get(route('seller.payment-methods.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('seller/payment-methods/index')
                ->has('banks')
                ->where('banks.0.id', 'absa')
                ->where('banks.0.label', 'ABSA'));
    }

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Bank Select Store',
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
