<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualFundingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_manual_funding_settings_via_post(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post(route('admin.manual-funding.settings.update'), [
            'enabled' => true,
            'instructions' => 'Send payment to CityShop MoMo, then upload proof.',
            'accounts' => [
                [
                    'type' => 'momo',
                    'label' => 'CITY SHOP MOMO',
                    'account_name' => 'CITY UNLOCK VENTURES',
                    'account_number' => '0539790093',
                    'network' => 'mtn',
                    'bank_name' => null,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settings = PlatformSettings::manualFundingAccounts();
        $this->assertTrue($settings['enabled']);
        $this->assertSame('CITY SHOP MOMO', $settings['accounts'][0]['label']);
        $this->assertSame('mtn', $settings['accounts'][0]['network']);
        $this->assertSame('0539790093', $settings['accounts'][0]['account_number']);
        // Known CityShop receive numbers always surface business + Robert Asare.
        $this->assertSame('City Unlock Ventures / Robert Asare', $settings['accounts'][0]['account_name']);
    }

    public function test_admin_momo_account_requires_network(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->from(route('admin.manual-funding.settings'))->post(
            route('admin.manual-funding.settings.update'),
            [
                'enabled' => true,
                'instructions' => 'Pay us',
                'accounts' => [
                    [
                        'type' => 'momo',
                        'label' => 'CITY SHOP MOMO',
                        'account_name' => 'CITY UNLOCK VENTURES',
                        'account_number' => '0539790093',
                        'network' => '',
                        'bank_name' => null,
                    ],
                ],
            ],
        );

        $response->assertRedirect(route('admin.manual-funding.settings'));
        $response->assertSessionHasErrors('accounts.0.network');
    }

    public function test_missing_telecel_and_airteltigo_are_filled_in(): void
    {
        PlatformSettings::saveManualFundingAccounts([
            'enabled' => true,
            'instructions' => 'Pay us',
            'accounts' => [
                [
                    'type' => 'momo',
                    'label' => 'MTN only',
                    'account_name' => 'CITY UNLOCK VENTURES',
                    'account_number' => '0539790093',
                    'network' => 'mtn',
                    'bank_name' => null,
                ],
            ],
        ]);

        $settings = PlatformSettings::manualFundingAccounts();
        $networks = collect($settings['accounts'])->pluck('network')->filter()->values()->all();

        $this->assertContains('mtn', $networks);
        $this->assertContains('telecel', $networks);
        $this->assertContains('airteltigo', $networks);

        $telecel = collect($settings['accounts'])->firstWhere('network', 'telecel');
        $this->assertSame('513014', $telecel['account_number']);
        $this->assertSame('City Unlock Ventures / Robert Asare', $telecel['account_name']);
    }

    public function test_normalize_momo_network_accepts_legacy_labels(): void
    {
        $this->assertSame('mtn', PlatformSettings::normalizeMomoNetwork('MTN Mobile Money'));
        $this->assertSame('telecel', PlatformSettings::normalizeMomoNetwork('Telecel Cash'));
        $this->assertSame('airteltigo', PlatformSettings::normalizeMomoNetwork('AirtelTigo Money'));
    }
}
