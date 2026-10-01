<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\FlutterwaveService;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlutterwaveKeysSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.flutterwave.public_key' => '',
            'services.flutterwave.secret_key' => '',
            'services.flutterwave.webhook_hash' => '',
        ]);
        $this->app->forgetInstance(FlutterwaveService::class);
    }

    public function test_admin_saved_keys_override_empty_env_and_make_flutterwave_available(): void
    {
        $this->assertFalse(app(FlutterwaveService::class)->isConfigured());

        Http::fake([
            'https://api.flutterwave.com/v3/banks/GH' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.flutterwave.keys.update'), [
                'public_key' => 'FLWPUBK-cityshopadminlive-X',
                'secret_key' => 'FLWSECK-cityshopadminlive-X',
                'webhook_hash' => 'CityUnlockFlwWh2026',
                'verify' => true,
            ])
            ->assertRedirect();

        $status = PlatformSettings::flutterwaveKeysStatus();
        $this->assertSame('admin', $status['source']);
        $this->assertTrue($status['configured']);
        $this->assertTrue(app(FlutterwaveService::class)->isAvailable());
        $this->assertSame('FLWPUBK-cityshopadminlive-X', app(FlutterwaveService::class)->publicKey());
    }

    public function test_admin_keys_override_env_keys(): void
    {
        config([
            'services.flutterwave.public_key' => 'FLWPUBK-fromenv-X',
            'services.flutterwave.secret_key' => 'FLWSECK-fromenv-X',
        ]);

        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => 'FLWPUBK-fromadmin-X',
            'secret_key' => 'FLWSECK-fromadmin-X',
        ]);

        $this->assertSame('FLWPUBK-fromadmin-X', PlatformSettings::resolvedFlutterwavePublicKey());
        $this->assertSame('FLWSECK-fromadmin-X', PlatformSettings::resolvedFlutterwaveSecretKey());
        $this->assertSame('admin', PlatformSettings::flutterwaveKeysStatus()['source']);
    }

    public function test_clearing_admin_keys_falls_back_to_env(): void
    {
        config([
            'services.flutterwave.public_key' => 'FLWPUBK-fromenv-X',
            'services.flutterwave.secret_key' => 'FLWSECK-fromenv-X',
        ]);
        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => 'FLWPUBK-fromadmin-X',
            'secret_key' => 'FLWSECK-fromadmin-X',
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->post(route('admin.flutterwave.keys.clear'))
            ->assertRedirect();

        $this->assertSame('FLWPUBK-fromenv-X', PlatformSettings::resolvedFlutterwavePublicKey());
        $this->assertSame('env', PlatformSettings::flutterwaveKeysStatus()['source']);
    }

    public function test_invalid_secret_format_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.flutterwave.keys.update'), [
                'public_key' => 'FLWPUBK-ok-X',
                'secret_key' => 'sk_live_not_flutterwave',
                'verify' => false,
            ])
            ->assertSessionHasErrors('secret_key');
    }

    public function test_verify_endpoint_surfaces_invalid_authorization_key(): void
    {
        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => 'FLWPUBK-cityshopadminlive-X',
            'secret_key' => 'FLWSECK-cityshopadminlive-X',
        ]);

        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'error',
                'message' => 'Invalid authorization key',
            ], 401),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->post(route('admin.flutterwave.keys.verify'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Invalid authorization key');
    }

    public function test_blank_key_fields_keep_the_saved_secret(): void
    {
        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => 'FLWPUBK-keepme-X',
            'secret_key' => 'FLWSECK-keepme-X',
        ]);

        Http::fake([
            'https://api.flutterwave.com/v3/banks/GH' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->post(route('admin.flutterwave.keys.update'), [
                'public_key' => 'FLWPUBK-updated-X',
                'secret_key' => '',
                'verify' => true,
            ])
            ->assertRedirect();

        $this->assertSame('FLWPUBK-updated-X', PlatformSettings::resolvedFlutterwavePublicKey());
        $this->assertSame('FLWSECK-keepme-X', PlatformSettings::resolvedFlutterwaveSecretKey());
    }
}
