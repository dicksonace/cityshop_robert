<?php

namespace Tests\Feature\Api;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAdminHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_on_the_admin_portal(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@cityshop.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@cityshop.com',
            'password' => 'password',
            'portal' => 'admin',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@cityshop.com',
            'password' => 'password',
            'portal' => 'buyer',
        ])->assertUnprocessable();
    }

    public function test_buyers_cannot_open_the_admin_api(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_open_dashboard_and_approve_a_seller(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sellerUser = User::factory()->create(['role' => UserRole::Seller]);
        $seller = SellerProfile::create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Pending Mart',
            'status' => SellerStatus::Pending,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.pending_sellers', 1)
            ->assertJsonPath('stats.pending_rmb', 0);

        $this->postJson("/api/v1/admin/sellers/{$seller->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_admin_can_manage_categories_buyers_and_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama Buyer']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Phones', 'sort_order' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Phones');

        $this->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Phones']);

        $this->getJson('/api/v1/admin/buyers?search=Ama')
            ->assertOk()
            ->assertJsonPath('data.0.id', $buyer->id);

        $this->getJson('/api/v1/admin/settings/sms')->assertOk()->assertJsonStructure(['settings', 'providers']);
        $this->getJson('/api/v1/admin/china-transfers')->assertOk()->assertJsonStructure(['data', 'dashboard']);
        $this->getJson('/api/v1/admin/sell-rmb')->assertOk()->assertJsonStructure(['data', 'dashboard']);
        $this->getJson('/api/v1/admin/transactions')->assertOk();
        $this->getJson('/api/v1/admin/chats')->assertOk();
        $this->getJson('/api/v1/admin/seller-invites')->assertOk();
        $this->getJson('/api/v1/admin/orders/awaiting-direct')->assertOk();
        $this->getJson('/api/v1/admin/orders/cancellations')->assertOk();
    }
}
