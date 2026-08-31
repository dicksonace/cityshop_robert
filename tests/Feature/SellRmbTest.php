<?php

namespace Tests\Feature;

use App\Enums\SellRmbStatus;
use App\Enums\UserRole;
use App\Models\SellRmbFormField;
use App\Models\SellRmbReceiveMethod;
use App\Models\SellRmbSetting;
use App\Models\SellRmbTransfer;
use App\Models\User;
use App\Services\SellRmbService;
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

    public function test_admin_mark_paid_requires_proof_and_complete_requires_paid_proof(): void
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
            ->assertSessionHasErrors('proof');

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.paid', $transfer->fresh()), [
                'payout_amount' => $transfer->usd_payout,
                'payout_ref' => 'MOMO-1',
                'proof' => UploadedFile::fake()->image('payout.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Paid, $transfer->fresh()->status);
        $this->assertTrue($transfer->fresh()->proofs()->where('type', 'payout_sent')->exists());

        $this->actingAs($admin)
            ->post(route('admin.sell-rmb.complete', $transfer->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertEquals(SellRmbStatus::Completed, $transfer->fresh()->status);
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
