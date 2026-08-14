<?php

namespace Tests\Feature;

use App\Enums\ChinaTransferStatus;
use App\Enums\UserRole;
use App\Models\ChinaTransfer;
use App\Models\ChinaTransferFormField;
use App\Models\ChinaTransferPaymentMethod;
use App\Models\ChinaTransferSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ChinaTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChinaTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_quote_uses_ghs_per_rmb_and_adds_flat_fee(): void
    {
        $this->openService();
        $quote = app(ChinaTransferService::class)->quote(5000);

        $this->assertEquals(5000.0, $quote['ghs_amount']);
        $this->assertEquals(1.85, $quote['ghs_per_rmb']);
        $this->assertEqualsWithDelta(2702.70, $quote['rmb_amount'], 0.001);
        $this->assertEquals(50.0, $quote['fee_ghs']);
        $this->assertEquals(5050.0, $quote['total_payable_ghs']);
    }

    public function test_buyer_wallet_opens_china_transfer_hub(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 80,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        $this->actingAs($buyer)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shop/wallet'));

        $this->actingAs($buyer)
            ->get(route('wallet.china-transfer.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/china-transfer/index')
                ->where('config.channel', 'alipay'));
    }

    public function test_buyer_cannot_create_when_service_is_closed(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($buyer)
            ->post(route('wallet.china-transfer.store'), ['ghs_amount' => 1000])
            ->assertSessionHasErrors('ghs_amount');
    }

    public function test_rate_is_locked_when_admin_publishes_a_new_rate(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $this->assertEquals(1.85, (float) $transfer->ghs_per_rmb);

        app(ChinaTransferService::class)->publishRate($opened['admin'], [
            'ghs_per_rmb' => 2.00,
            'fee_mode' => 'flat',
            'fee_value' => 50,
            'min_ghs' => 50,
            'max_ghs' => 50000,
        ]);

        $this->assertEquals(1.85, (float) $transfer->fresh()->ghs_per_rmb);
        $this->assertEquals(2.0, (float) app(ChinaTransferService::class)->currentRate()->ghs_per_rmb);
    }

    public function test_complete_requires_rmb_proof_and_then_is_immutable(): void
    {
        Storage::fake('public');
        Notification::fake();

        $opened = $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = $opened['admin'];
        $service = app(ChinaTransferService::class);
        $transfer = $this->submitTransfer($buyer, $opened['method']);

        $service->verifyPayment($transfer, $admin);
        $service->startProcessing($transfer->fresh(), $admin);

        $this->actingAs($admin)
            ->post(route('admin.china-transfers.complete', $transfer))
            ->assertSessionHasErrors();

        $this->actingAs($admin)
            ->post(route('admin.china-transfers.sent', $transfer->fresh()), [
                'rmb_sent_amount' => $transfer->rmb_amount,
                'rmb_transfer_ref' => 'ALI-1',
                'proof' => UploadedFile::fake()->image('rmb.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.china-transfers.complete', $transfer->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertEquals(ChinaTransferStatus::Completed, $transfer->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.china-transfers.verify', $transfer->fresh()))
            ->assertSessionHasErrors('status');
    }

    public function test_api_buyer_can_load_config_without_wechat(): void
    {
        $this->openService();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/wallet/china-transfer')
            ->assertOk()
            ->assertJsonPath('config.channel', 'alipay')
            ->assertJsonPath('config.channel_label', 'Alipay');
    }

    /**
     * @return array{admin: User, method: ChinaTransferPaymentMethod}
     */
    private function openService(): array
    {
        ChinaTransferSetting::current()->update(['enabled' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        app(ChinaTransferService::class)->publishRate($admin, [
            'ghs_per_rmb' => 1.85,
            'fee_mode' => 'flat',
            'fee_value' => 50,
            'min_ghs' => 50,
            'max_ghs' => 50000,
        ]);
        $method = ChinaTransferPaymentMethod::create([
            'name' => 'MTN MoMo',
            'type' => 'momo',
            'account_name' => 'CityShop',
            'account_number' => '0240000000',
            'instructions' => 'Send the exact amount',
            'proof_required' => true,
            'active' => true,
            'sort_order' => 1,
        ]);

        return ['admin' => $admin, 'method' => $method];
    }

    private function submitTransfer(User $buyer, ChinaTransferPaymentMethod $method): ChinaTransfer
    {
        $payload = [
            'ghs_amount' => 1000,
            'payment_method_id' => $method->id,
            'fields' => [],
            'files' => [],
        ];

        foreach (ChinaTransferFormField::query()->where('active', true)->get() as $field) {
            if ($field->isFile()) {
                $payload['files'][$field->id] = UploadedFile::fake()->image($field->name.'.jpg');
            } elseif ($field->required) {
                $payload['fields'][$field->id] = $field->type === 'email' ? 'ali@example.com' : 'Test value';
            }
        }

        $this->actingAs($buyer)
            ->post(route('wallet.china-transfer.store'), $payload)
            ->assertRedirect();

        return ChinaTransfer::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
    }
}
