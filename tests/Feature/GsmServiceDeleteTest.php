<?php

namespace Tests\Feature;

use App\Enums\GsmServiceType;
use App\Enums\UserRole;
use App\Models\GsmService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GsmServiceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_delete_a_gsm_service(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $service = GsmService::query()->create([
            'name' => 'Galaxy Multi Tool -1 Year',
            'slug' => 'galaxy-multi-tool-1-year-test',
            'service_type' => GsmServiceType::Imei,
            'price_ghs' => 250,
            'currency' => 'GHS',
            'sort_order' => 0,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.gsm-tools.services.destroy', $service))
            ->assertRedirect(route('admin.gsm-tools.services', ['type' => 'imei']));

        $this->assertDatabaseMissing('gsm_services', ['id' => $service->id]);
    }
}
