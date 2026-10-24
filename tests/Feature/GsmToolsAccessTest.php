<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GsmToolsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_gsm_tools_is_hidden_until_admin_enables_the_account(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->assertFalse($buyer->canUseGsmTools());

        $this->actingAs($buyer)
            ->get(route('gsm-tools.index'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canUseGsmTools', false));
    }

    public function test_admin_can_enable_and_disable_gsm_tools_for_a_buyer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($admin)
            ->post(route('admin.buyers.gsm-tools', $buyer), ['enabled' => true])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertTrue($buyer->gsm_tools_enabled);
        $this->assertTrue($buyer->canUseGsmTools());

        $this->actingAs($buyer)
            ->get(route('gsm-tools.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.buyers.gsm-tools', $buyer), ['enabled' => false])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertFalse($buyer->canUseGsmTools());
        $this->actingAs($buyer)->get(route('gsm-tools.index'))->assertForbidden();
    }

    public function test_turning_gsm_tools_off_for_everyone_hides_an_enabled_engineer(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'gsm_tools_enabled' => true,
        ]);
        $this->assertTrue($buyer->canUseGsmTools());

        PlatformSettings::setGsmToolsGloballyEnabled(false);
        $this->assertFalse($buyer->fresh()->canUseGsmTools());
        $this->actingAs($buyer)->get(route('gsm-tools.index'))->assertForbidden();
    }
}
