<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\GsmServiceType;
use App\Enums\UserRole;
use App\Models\GsmService;
use App\Models\User;
use App\Notifications\GsmOrderAdminNotification;
use App\Services\GsmToolService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GsmOrderAdminSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_gsm_order_sms_and_email_all_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'name' => 'Admin One',
            'mobile' => '0244000000',
        ]);
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi Amoah',
        ]);
        WalletService::creditFromVerifiedTopUp($buyer->id, 500, 'GSM-SMS-TEST', 'admin');

        $service = GsmService::query()->create([
            'name' => 'Galaxy Multi Tool',
            'slug' => 'galaxy-multi-tool-sms-test',
            'service_type' => GsmServiceType::Imei,
            'price_ghs' => 20,
            'currency' => 'GHS',
            'sort_order' => 0,
            'active' => true,
            'allow_quantity' => false,
        ]);

        $order = app(GsmToolService::class)->createOrder($buyer, Request::create('/gsm-tools/orders', 'POST', [
            'gsm_service_id' => $service->id,
        ]));

        Notification::assertSentTo($admin, GsmOrderAdminNotification::class, function ($notification, $channels) use ($order) {
            return $notification->order->is($order)
                && in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });

        $sms = (new GsmOrderAdminNotification($order->load('user')))->toSms($admin);
        $this->assertStringContainsString('Kofi Amoah placed GSM Tools Galaxy Multi Tool', $sms);
        $this->assertStringContainsString($order->reference, $sms);
        $this->assertStringContainsString('GHS 20.00', $sms);
        $this->assertStringContainsString('Review in admin', $sms);
        $this->assertStringNotContainsString('₵', $sms);
    }
}
