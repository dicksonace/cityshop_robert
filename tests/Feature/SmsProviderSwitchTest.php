<?php

namespace Tests\Feature;

use App\Services\PlatformSettings;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsProviderSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_txtconnect_sends_post_body_with_bearer_token(): void
    {
        config([
            'services.sms.driver' => 'txtconnect',
            'services.sms.txtconnect_api_key' => 'txt-test-key',
            'services.sms.txtconnect_sender' => 'CityShop',
            'services.sms.txtconnect_base_url' => 'https://api.txtconnect.net/dev/api',
        ]);

        Http::fake([
            'https://api.txtconnect.net/dev/api/sms/send' => Http::response([
                'messageId' => 'RRTAPPLMUZRX',
                'msg' => 'Sms send Successful',
                'data' => [
                    'status_code' => '000',
                    'message' => 'Sms send Successful',
                    'in_error' => false,
                    'reason' => 'Sms send Successful',
                ],
            ], 200),
        ]);

        $ok = app(SmsService::class)->send('0532700209', 'CityShop: payment Completed');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/sms/send')
                && $request->hasHeader('Authorization', 'Bearer txt-test-key')
                && ($payload['to'] ?? null) === '233532700209'
                && ($payload['from'] ?? null) === 'CityShop'
                && ($payload['unicode'] ?? null) === 'regular'
                && ($payload['sms'] ?? null) === 'CityShop: payment Completed';
        });
    }

    public function test_admin_can_switch_sms_platform(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sms.settings.update'), [
                'driver' => 'txtconnect',
                'failover' => true,
            ])
            ->assertRedirect();

        $this->assertSame('txtconnect', PlatformSettings::smsDriver());
        $this->assertTrue(PlatformSettings::smsFailoverEnabled());
    }

    public function test_admin_can_save_finance_alert_numbers(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sms.settings.update'), [
                'driver' => 'formula_dc',
                'failover' => false,
                'alert_mobile_1' => '0248620718',
                'alert_mobile_2' => '0539790093',
                'alert_mobile_3' => '0591456140',
                'alert_mobile_4' => '0273706541',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['0248620718', '0539790093', '0591456140', '0273706541'],
            PlatformSettings::adminAlertNumbers(),
        );
    }

    public function test_failover_uses_the_other_platform(): void
    {
        config([
            'services.sms.driver' => 'formula_dc',
            'services.sms.formula_dc_api_key' => 'formula-key',
            'services.sms.txtconnect_api_key' => 'txt-test-key',
            'services.sms.txtconnect_sender' => 'CityShop',
        ]);
        PlatformSettings::saveSmsSettings([
            'driver' => 'formula_dc',
            'failover' => true,
        ]);

        Http::fake([
            'https://api.formula-dc.com/api/v1/external/sms/send' => Http::response(['success' => false], 500),
            'https://api.txtconnect.net/dev/api/sms/send' => Http::response([
                'messageId' => 'FALLBACK1',
                'data' => ['status_code' => '000', 'in_error' => false],
            ], 200),
        ]);

        $ok = app(SmsService::class)->send('0248620718', 'CityShop test');

        $this->assertTrue($ok);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'formula-dc.com'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'txtconnect.net'));
    }
}
