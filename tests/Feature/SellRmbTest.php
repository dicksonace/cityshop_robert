<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\SellRmbStatus;
use App\Enums\UserRole;
use App\Models\SellRmbFormField;
use App\Models\SellRmbReceiveMethod;
use App\Models\SellRmbSetting;
use App\Models\SellRmbTransfer;
use App\Models\User;
use App\Notifications\SellRmbUserNotification;
use App\Services\SellRmbService;
use App\Support\SellRmbSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellRmbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_quote_math_uses_usd_per_rmb_and_fee(): void
    {
        $this->openService();
        $quote = app(SellRmbService::class)->quote(1000, 'usd');

        // usd_gross = 1000 * 0.14 = 140; fee flat 1; usd_payout = 139; ghs = 139 * 15.5
        $this->assertEquals(1000.0, $quote['rmb_amount']);
        $this->assertEquals(0.14, $quote['usd_per_rmb']);
        $this->assertEquals(140.0, $quote['usd_gross']);
        $this->assertEquals(1.0, $quote['fee_usd']);
        $this->assertEquals(139.0, $quote['usd_payout']);
        $this->assertEqualsWithDelta(2154.5, $quote['ghs_payout'], 0.001);
    }

    public function test_buyer_cannot_create_when_disabled(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/sell-rmb', [
            'rmb_amount' => 1000,
            'payout_currency' => 'usd',
            'receive_method_id' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('rmb_amount');
    }

    public function test_admin_can_store_alipay_method_from_multipart_form(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);

        $this->post('/api/v1/admin/sell-rmb/methods', [
            'name' => 'Alipay',
            'type' => 'alipay',
            'account_name' => 'RMB Wallet',
            'proof_required' => 'true',
            'active' => 'true',
            'qr' => UploadedFile::fake()->image('alipay-qr.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.account_name', 'RMB Wallet');

        $this->assertDatabaseHas('sell_rmb_receive_methods', [
            'type' => 'alipay',
            'account_name' => 'RMB Wallet',
            'proof_required' => true,
            'active' => true,
        ]);
    }

    public function test_config_exposes_live_separately_from_open(): void
    {
        Storage::fake('public');
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        SellRmbSetting::current()->update(['enabled' => true]);
        app(SellRmbService::class)->publishRate(User::factory()->create(['role' => UserRole::Admin]), [
            'ghs_per_rmb' => 1.25,
            'fee_mode' => 'flat',
            'fee_value' => 0,
            'min_rmb' => 100,
            'max_rmb' => 50000,
        ]);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/wallet/sell-rmb')
            ->assertOk()
            ->assertJsonPath('config.live', true)
            ->assertJsonPath('config.open', false)
            ->assertJsonPath('config.enabled', false)
            ->assertJsonPath('config.readiness.rate_published', true)
            ->assertJsonPath('config.readiness.alipay_qr', false)
            ->assertJsonPath('config.status_message', 'Alipay QR not uploaded yet.')
            ->assertJsonCount(0, 'config.receive_methods');

        SellRmbReceiveMethod::create([
            'name' => 'Alipay',
            'type' => 'alipay',
            'account_name' => 'CityShop',
            'active' => true,
            'sort_order' => 1,
            'qr_path' => 'sell-rmb/methods/test-qr.png',
        ]);

        $this->getJson('/api/v1/wallet/sell-rmb')
            ->assertOk()
            ->assertJsonPath('config.live', true)
            ->assertJsonPath('config.open', true)
            ->assertJsonPath('config.enabled', true);

        SellRmbSetting::current()->update(['enabled' => false]);

        $this->getJson('/api/v1/wallet/sell-rmb')
            ->assertOk()
            ->assertJsonPath('config.live', false)
            ->assertJsonPath('config.open', false)
            ->assertJsonPath('config.enabled', false);
    }

    public function test_create_locks_rate_snapshot(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $this->assertEquals(0.14, (float) $transfer->usd_per_rmb);
        $this->assertEquals(15.5, (float) $transfer->ghs_per_usd);
        $this->assertEquals(SellRmbStatus::Submitted, $transfer->status);

        app(SellRmbService::class)->publishRate($opened['admin'], [
            'usd_per_rmb' => 0.15,
            'ghs_per_usd' => 16,
            'fee_mode' => 'flat',
            'fee_value' => 1,
            'min_rmb' => 100,
            'max_rmb' => 50000,
        ]);

        $this->assertEquals(0.14, (float) $transfer->fresh()->usd_per_rmb);
        $this->assertEquals(0.15, (float) app(SellRmbService::class)->currentRate()->usd_per_rmb);
    }

    public function test_buyer_show_returns_processing_payload_after_ghs_submit(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        app(SellRmbService::class)->publishRate($opened['admin'], [
            'ghs_per_rmb' => 1.712,
            'fee_mode' => 'flat',
            'fee_value' => 0,
            'min_rmb' => 20,
            'max_rmb' => 50000,
        ]);

        $payload = [
            'rmb_amount' => 500,
            'payout_currency' => 'ghs',
            'receive_method_id' => $opened['method']->id,
            'fields' => [],
            'files' => [],
        ];

        foreach (SellRmbFormField::query()->where('active', true)->get() as $field) {
            if ($field->isFile()) {
                $payload['files'][$field->id] = UploadedFile::fake()->image($field->name.'.jpg');
            } elseif ($field->required) {
                $payload['fields'][$field->id] = $field->type === 'phone' ? '0530790002' : 'Robert Asare';
            }
        }

        Sanctum::actingAs($buyer);
        $create = $this->postJson('/api/v1/wallet/sell-rmb', $payload)->assertCreated();
        $id = $create->json('data.id');

        $this->getJson("/api/v1/wallet/sell-rmb/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.status_label', 'Processing')
            ->assertJsonPath('data.processing', true)
            ->assertJsonPath('data.status_presentation.header_title', 'Awaiting Review')
            ->assertJsonPath('data.status_presentation.badge_label', 'Pending')
            ->assertJsonPath('data.quote.payout_currency', 'ghs');
    }

    public function test_sell_submit_sms_uses_rmb_wallet_style(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Robert Asare',
            'mobile' => '0248620718',
        ]);

        $payload = [
            'rmb_amount' => 100,
            'payout_currency' => 'ghs',
            'receive_method_id' => $opened['method']->id,
            'fields' => [],
            'files' => [],
        ];

        foreach (SellRmbFormField::query()->where('active', true)->get() as $field) {
            if ($field->name === 'payout_bank_name') {
                $payload['fields'][$field->id] = 'MTN';
            } elseif ($field->name === 'payout_mobile') {
                $payload['fields'][$field->id] = '+233248620718';
            } elseif ($field->name === 'payout_name') {
                $payload['fields'][$field->id] = 'Robert Asare';
            } elseif ($field->isFile()) {
                $payload['files'][$field->id] = UploadedFile::fake()->image($field->name.'.jpg');
            } elseif ($field->required) {
                $payload['fields'][$field->id] = $field->type === 'phone' ? '0248620718' : 'Test value';
            }
        }

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/wallet/sell-rmb', $payload)->assertCreated();

        $transfer = SellRmbTransfer::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
        $sms = SellRmbSms::userMessage($transfer->fresh(), SellRmbStatus::Submitted, 'Robert Asare');

        $this->assertStringContainsString('Hi Robert Asare, RMB Sell submitted.', $sms);
        $this->assertStringContainsString('Rmb 100.00 for Ghc', $sms);
        $this->assertStringContainsString('Payout via MTN.', $sms);
        $this->assertStringContainsString('Pending Review.', $sms);

        Notification::assertSentTo($buyer, SellRmbUserNotification::class, function (SellRmbUserNotification $notification) use ($buyer) {
            $text = $notification->toSms($buyer);

            return str_contains($text, 'Hi Robert Asare, RMB Sell submitted.')
                && str_contains($text, 'Pending Review.')
                && ! str_contains($text, 'CityShop:');
        });
    }

    public function test_intermediate_sell_statuses_do_not_send_sms(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Robert Asare', 'mobile' => '0248620718']);
        $admin = $opened['admin'];
        $service = app(SellRmbService::class);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        Notification::assertSentTo($buyer, SellRmbUserNotification::class, function (SellRmbUserNotification $notification) use ($buyer) {
            return $notification->status === SellRmbStatus::Submitted
                && in_array(SmsChannel::class, $notification->via($buyer), true);
        });

        $service->startVerification($transfer, $admin);
        $service->markRmbReceived($transfer->fresh(), $admin);
        $service->startPayoutProcessing($transfer->fresh(), $admin);

        foreach ([SellRmbStatus::RmbVerification, SellRmbStatus::RmbReceived, SellRmbStatus::PayoutProcessing] as $status) {
            Notification::assertSentTo($buyer, SellRmbUserNotification::class, function (SellRmbUserNotification $notification) use ($buyer, $status) {
                return $notification->status === $status
                    && ! in_array(SmsChannel::class, $notification->via($buyer), true);
            });
        }

        $this->assertFalse(SellRmbSms::sendsToUser(SellRmbStatus::RmbVerification));
        $this->assertTrue(SellRmbSms::sendsToUser(SellRmbStatus::Completed));
    }

    public function test_admin_complete_without_payout_proof(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = $opened['admin'];
        $service = app(SellRmbService::class);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $service->markReadyForPayout($transfer, $admin);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.approve-payout', $transfer->fresh()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Completed, $transfer->fresh()->status);
        $this->assertFalse($transfer->fresh()->proofs()->where('type', 'payout_sent')->exists());
    }

    public function test_admin_mark_paid_allows_optional_proof(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = $opened['admin'];
        $service = app(SellRmbService::class);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $service->startVerification($transfer, $admin);
        $service->markRmbReceived($transfer->fresh(), $admin);
        $service->startPayoutProcessing($transfer->fresh(), $admin);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.paid', $transfer->fresh()), [
                'payout_amount' => $transfer->usd_payout,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Paid, $transfer->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.complete', $transfer->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Completed, $transfer->fresh()->status);
    }

    public function test_admin_mark_processing_and_approve_payout_flow(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = $opened['admin'];
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.mark-processing', $transfer))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::PayoutProcessing, $transfer->fresh()->status);

        $adminPayload = app(SellRmbService::class)->transferPayload($transfer->fresh(), true);
        $this->assertSame('send_momo', $adminPayload['admin_queue_section'] ?? null);
        $this->assertArrayHasKey('payout_account', $adminPayload);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.approve-payout', $transfer->fresh()), [
                'proof' => UploadedFile::fake()->image('momo.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Completed, $transfer->fresh()->status);
        $this->assertTrue($transfer->fresh()->proofs()->where('type', 'payout_sent')->exists());
    }

    public function test_cannot_edit_after_completed(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = $opened['admin'];
        $service = app(SellRmbService::class);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $service->startVerification($transfer, $admin);
        $service->markRmbReceived($transfer->fresh(), $admin);
        $service->startPayoutProcessing($transfer->fresh(), $admin);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.paid', $transfer->fresh()), [
                'payout_amount' => $transfer->usd_payout,
                'proof' => UploadedFile::fake()->image('payout.png'),
            ])
            ->assertSessionHasNoErrors();

        $service->complete($transfer->fresh(), $admin);

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.verify', $transfer->fresh()))
            ->assertSessionHasErrors('status');
    }

    /**
     * @return array{admin: User, method: SellRmbReceiveMethod}
     */
    private function openService(): array
    {
        SellRmbSetting::current()->update(['enabled' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        app(SellRmbService::class)->publishRate($admin, [
            'usd_per_rmb' => 0.14,
            'ghs_per_usd' => 15.5,
            'fee_mode' => 'flat',
            'fee_value' => 1,
            'min_rmb' => 100,
            'max_rmb' => 50000,
        ]);
        $method = SellRmbReceiveMethod::create([
            'name' => 'CityShop Alipay',
            'type' => 'alipay',
            'account_name' => 'CityShop',
            'account_number' => 'ali@cityshop.com',
            'instructions' => 'Send exact RMB',
            'proof_required' => true,
            'active' => true,
            'sort_order' => 1,
            'qr_path' => 'sell-rmb/methods/test-qr.png',
        ]);

        return ['admin' => $admin, 'method' => $method];
    }

    private function submitTransfer(User $buyer, SellRmbReceiveMethod $method): SellRmbTransfer
    {
        $payload = [
            'rmb_amount' => 1000,
            'payout_currency' => 'usd',
            'receive_method_id' => $method->id,
            'fields' => [],
            'files' => [],
        ];

        foreach (SellRmbFormField::query()->where('active', true)->get() as $field) {
            if ($field->isFile()) {
                $payload['files'][$field->id] = UploadedFile::fake()->image($field->name.'.jpg');
            } elseif ($field->required) {
                $payload['fields'][$field->id] = $field->type === 'phone' ? '0240000000' : 'Test value';
            }
        }

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/wallet/sell-rmb', $payload)
            ->assertCreated();

        return SellRmbTransfer::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
    }
}
