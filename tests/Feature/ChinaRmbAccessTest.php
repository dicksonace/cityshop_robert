<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChinaRmbAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_china_rmb_is_off_until_admin_enables_it(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->assertFalse($buyer->canUseRmbWallet());

        $this->actingAs($buyer)
            ->get(route('wallet.china-rmb.index'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/wallet')
                ->where('canUseRmbWallet', false));
    }

    public function test_admin_can_enable_china_rmb_for_a_buyer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($admin)
            ->post(route('admin.buyers.china-rmb', $buyer), ['enabled' => true])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertTrue($buyer->china_rmb_enabled);
        $this->assertTrue($buyer->canUseRmbWallet());

        $this->actingAs($buyer)
            ->get(route('wallet.china-rmb.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.buyers.china-rmb', $buyer), ['enabled' => false])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertFalse($buyer->china_rmb_enabled);
        $this->assertFalse($buyer->canUseRmbWallet());

        $this->actingAs($buyer)
            ->get(route('wallet.china-rmb.index'))
            ->assertForbidden();
    }
}
