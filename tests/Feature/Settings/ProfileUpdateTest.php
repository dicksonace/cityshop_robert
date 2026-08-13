<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_seller_profile_page_shows_phone_number()
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'mobile' => '0532700209',
        ]);

        $this->actingAs($seller)
            ->get('/settings/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/profile')
                ->where('auth.user.role', 'seller')
                ->where('auth.user.mobile', '0532700209')
            );
    }

    public function test_buyers_cannot_update_profile_details()
    {
        $buyer = User::factory()->create([
            'name' => 'Kofi Buyer',
            'email' => 'kofi@example.com',
        ]);

        $this->actingAs($buyer)
            ->patch('/settings/profile', [
                'name' => 'Hacked Name',
                'email' => 'hacked@example.com',
            ])
            ->assertForbidden();

        $buyer->refresh();
        $this->assertSame('Kofi Buyer', $buyer->name);
        $this->assertSame('kofi@example.com', $buyer->email);
    }

    public function test_sellers_cannot_update_profile_details()
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'name' => 'Asare Kwame',
            'email' => 'eskimlitcenter1@gmail.com',
            'mobile' => '0241112223',
        ]);

        $this->actingAs($seller)
            ->patch('/settings/profile', [
                'name' => 'Hacked Seller',
                'email' => 'hacked.seller@example.com',
            ])
            ->assertForbidden();

        $seller->refresh();
        $this->assertSame('Asare Kwame', $seller->name);
        $this->assertSame('eskimlitcenter1@gmail.com', $seller->email);
        $this->assertSame('0241112223', $seller->mobile);
    }

    public function test_admin_can_update_own_profile_information()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch('/settings/profile', [
                'name' => 'Test Admin',
                'email' => 'admin.test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $admin->refresh();

        $this->assertSame('Test Admin', $admin->name);
        $this->assertSame('admin.test@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this
            ->actingAs($admin)
            ->patch('/settings/profile', [
                'name' => 'Test Admin',
                'email' => $admin->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($admin->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_sellers_cannot_delete_their_account()
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $response = $this
            ->actingAs($seller)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHas('error');

        $this->assertNotNull($seller->fresh());
        $this->assertAuthenticatedAs($seller);
    }

    public function test_seller_profile_page_hides_delete_account()
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($seller)
            ->get('/settings/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/profile')
                ->where('auth.user.role', 'seller')
            );
    }
}
