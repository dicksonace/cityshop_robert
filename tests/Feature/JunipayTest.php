<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\JunipayPayment;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\PlatformSettings;
use App\Services\WalletService;
use App\Services\WithdrawalPayoutService;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JunipayTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.junipayments.com';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        config([
            'services.junipay.client_id' => 'Fu812152',
            'services.junipay.private_key' => $pem,
        ]);
    }

    public function test_set_keys_command_saves_encrypted_key_from_file(): void
    {
        Http::fake([self::API.'/accountbalance' => Http::response(['available_balance' => 12.5])]);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $file = tempnam(sys_get_temp_dir(), 'jp');
        file_put_contents($file, $pem);

        $this->artisan('cityshop:set-junipay-keys', ['--client-id' => 'CLI123', '--key-file' => $file])
            ->expectsOutputToContain('Junipay accepted these keys')
            ->assertSuccessful();

        $stored = \App\Services\PlatformSettings::storedJunipayKeys();
        $this->assertSame('CLI123', $stored['client_id']);
        $this->assertSame(trim($pem), trim($stored['private_key']));
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode(\App\Models\PlatformSetting::query()->pluck('value')));

        $this->artisan('cityshop:set-junipay-keys', ['--client-id' => 'CLI123', '--key-file' => '/nope/missing.key'])
            ->assertFailed();

        @unlink($file);
    }

    private function verifiedBuyer(): User
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Ama Mensah',
            'mobile' => '0248620718',
        ]);
        KycVerification::create([
            'user_id' => $buyer->id,
            'ghana_card_number' => 'GHA-123456789-0',
            'full_name' => $buyer->name,
            'front_path' => 'kyc/front/a.jpg',
            'back_path' => 'kyc/back/b.jpg',
            'status' => KycStatus::Approved,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        return $buyer;
    }

    public function test_wallet_recharge_sends_momo_prompt_and_credits_once_when_paid(): void
    {
        $buyer = $this->verifiedBuyer();

        Http::fake([
            self::API.'/payment' => Http::response([
                'status' => 'pending',
                'message' => 'TRANSACTION PENDING',
                'transID' => 'COL100001',
            ], 200),
            self::API.'/checktranstatus' => Http::response([
                'code' => 200,
                'status' => 'SUCCESS',
                'info' => ['status' => 'SUCCESS', 'transID' => 'COL100001'],
            ], 200),
        ]);

        $response = $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds.junipay'), ['amount' => 20, 'method' => 'momo'])
            ->assertOk()
            ->assertJsonPath('gateway', 'junipay');

        $payUrl = (string) $response->json('authorization_url');
        $this->assertStringContainsString('/pay/junipay/', $payUrl);

        $this->get($payUrl)->assertOk()->assertSee('Pay with Mobile Money');

        $reference = (string) $response->json('reference');
        $record = JunipayPayment::where('reference', $reference)->firstOrFail();
        $this->assertSame('wallet_topup', $record->meta['type']);

        $startUrl = \URL::temporarySignedRoute('junipay.pay.start', now()->addHour(), ['reference' => $reference]);
        $this->postJson($startUrl, ['phone' => '024 862 0718', 'network' => 'telecel'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        Http::assertSent(function ($request) use ($reference) {
            return $request->url() === self::API.'/payment'
                && $request->hasHeader('clientid', 'Fu812152')
                && str_starts_with((string) $request->header('Authorization')[0], 'Bearer ey')
                && $request['phoneNumber'] === '0248620718'
                && $request['provider'] === 'vodafone'
                && $request['foreignID'] === 'CSJ'.$reference
                && strlen($request['foreignID']) >= 13
                && str_ends_with((string) $request['callbackUrl'], '/webhooks/junipay');
        });

        $statusUrl = \URL::temporarySignedRoute('junipay.pay.status', now()->addHour(), ['reference' => $reference]);
        $redirect = (string) $this->getJson($statusUrl)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->json('redirect');
        $this->assertStringContainsString('/wallet/junipay/callback', $redirect);

        $this->postJson('/webhooks/junipay', ['status' => 'success', 'trans_id' => 'COL100001', 'foreignID' => 'CSJ'.$reference])
            ->assertOk();

        $this->assertSame(1, WalletTransaction::where('reference', $reference)->count());
    }

    public function test_prompt_reads_nested_transaction_id_and_shows_junipay_errors(): void
    {
        $buyer = $this->verifiedBuyer();

        Http::fake([
            self::API.'/payment' => Http::sequence()
                ->push(['code' => 401, 'status' => 'failed', 'message' => 'Phone number is not registered to this network'], 200)
                ->push(['code' => 200, 'info' => ['status' => 'pending', 'transID' => 'NEST77']], 200),
            self::API.'/checktranstatus' => Http::response(['code' => 200, 'info' => ['status' => 'PENDING']], 200),
        ]);

        $reference = (string) $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds.junipay'), ['amount' => 20, 'method' => 'momo'])
            ->assertOk()
            ->json('reference');

        $startUrl = \URL::temporarySignedRoute('junipay.pay.start', now()->addHour(), ['reference' => $reference]);
        $this->postJson($startUrl, ['phone' => '0248620718', 'network' => 'mtn'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Phone number is not registered to this network');

        $this->postJson($startUrl, ['phone' => '0248620718', 'network' => 'mtn'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');
        $this->assertSame('NEST77', JunipayPayment::where('reference', $reference)->value('trans_id'));

        $statusUrl = \URL::temporarySignedRoute('junipay.pay.status', now()->addHour(), ['reference' => $reference]);
        $this->getJson($statusUrl)->assertOk()->assertJsonPath('status', 'pending');

        Http::assertSent(fn ($request) => $request->url() === self::API.'/checktranstatus' && $request['transID'] === 'NEST77');
    }

    public function test_callback_with_unknown_transaction_id_is_ignored(): void
    {
        $buyer = $this->verifiedBuyer();
        JunipayPayment::create([
            'reference' => 'JUNI1',
            'user_id' => $buyer->id,
            'amount' => 50,
            'trans_id' => 'COL-REAL',
            'meta' => ['type' => 'wallet_topup', 'user_id' => $buyer->id, 'method' => 'momo', 'wallet_credit' => 50, 'expected_amount' => 50],
        ]);

        Http::fake([self::API.'/checktranstatus' => Http::response(['status' => 'SUCCESS', 'info' => ['status' => 'SUCCESS']], 200)]);

        $this->postJson('/webhooks/junipay', ['status' => 'success', 'trans_id' => 'COL-FAKE', 'foreignID' => 'JUNI1'])->assertOk();

        $this->assertSame(0, WalletTransaction::where('reference', 'JUNI1')->count());
        Http::assertNothingSent();
    }

    public function test_admin_sends_withdrawal_through_junipay_and_callback_completes_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = $this->verifiedBuyer();
        WalletService::ensure($buyer);
        $withdrawal = Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 30,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'telecel',
            'status' => WithdrawalStatus::Pending,
        ]);

        Http::fake([
            self::API.'/transfer' => Http::response(['status' => 'pending', 'message' => 'TRANSFER PENDING', 'transID' => 'DIS100001'], 200),
            self::API.'/checktranstatus' => Http::response(['status' => 'SUCCESS', 'info' => ['status' => 'SUCCESS']], 200),
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/junipay")
            ->assertOk()
            ->assertJsonPath('data.junipay_trans_id', 'DIS100001');

        $withdrawal->refresh();
        $this->assertSame(WithdrawalStatus::Processing, $withdrawal->status);
        $this->assertSame('junipay', $withdrawal->payout_channel);

        Http::assertSent(fn ($request) => $request->url() === self::API.'/transfer'
            && $request['channel'] === 'mobile_money'
            && $request['provider'] === 'vodafone'
            && $request['phoneNumber'] === '0539790093'
            && (float) $request['amount'] === 30.0
            && $request['foreignID'] === $withdrawal->junipay_reference
            && $request['narration'] === 'CityShop withdrawal '.$withdrawal->id);

        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/junipay")->assertStatus(422);

        $this->postJson('/webhooks/junipay', [
            'status' => 'success',
            'trans_id' => 'DIS100001',
            'foreignID' => $withdrawal->junipay_reference,
        ])->assertOk();

        $this->assertSame(WithdrawalStatus::Paid, $withdrawal->fresh()->status);
    }

    public function test_auto_payout_sends_new_withdrawals_and_the_waiting_queue(): void
    {
        PlatformSettings::saveJunipaySettings([
            'collections_enabled' => true,
            'payouts_enabled' => true,
            'auto_payout' => true,
            'sandbox' => false,
        ]);

        Http::fake([
            self::API.'/transfer' => Http::sequence()
                ->push(['code' => 200, 'info' => ['transID' => 'DIS900', 'status' => 'pending', 'message' => 'TRANSACTION PENDING']], 200)
                ->push(['code' => 200, 'info' => ['transID' => 'DIS901', 'status' => 'pending']], 200),
            self::API.'/checktranstatus' => function ($request) {
                if ($request['transID'] === 'JPALREADY') {
                    return Http::response(['code' => 200, 'status' => 'pending', 'info' => ['status' => 'pending', 'partnerTransID' => 'JPALREADY']], 200);
                }

                return Http::response(['code' => 200, 'info' => 'Transaction not found'], 200);
            },
        ]);

        $buyer = $this->verifiedBuyer();
        $wallet = WalletService::ensure($buyer);
        $wallet->update(['available_balance' => 200]);

        $sent = app(WithdrawalRequestService::class)->submit($buyer, [
            'amount' => 20,
            'payout_type' => 'momo',
            'momo_number' => '0248620718',
            'account_name' => 'Ama Mensah',
            'network' => 'mtn',
        ]);
        $this->assertSame(WithdrawalStatus::Processing, $sent['withdrawal']->status);
        $this->assertSame('DIS900', $sent['withdrawal']->junipay_trans_id);
        $this->assertSame('junipay', $sent['withdrawal']->payout_channel);

        $waiting = Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 15,
            'momo_number' => '0248620718',
            'account_name' => 'Ama Mensah',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Pending,
        ]);
        $alreadySent = Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 10,
            'momo_number' => '0248620718',
            'account_name' => 'Ama Mensah',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Pending,
            'junipay_reference' => 'JPALREADY',
        ]);

        $stats = app(WithdrawalPayoutService::class)->dispatchAutoJunipayPayouts();
        $this->assertSame(2, $stats['sent']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame('DIS901', $waiting->fresh()->junipay_trans_id);
        $this->assertSame('JPALREADY', $alreadySent->fresh()->junipay_trans_id);
        $this->assertSame(WithdrawalStatus::Processing, $alreadySent->fresh()->status);

        Http::assertSentCount(3);
    }

    public function test_failed_junipay_payout_refunds_only_after_junipay_confirms(): void
    {
        $buyer = $this->verifiedBuyer();
        $wallet = WalletService::ensure($buyer);
        $wallet->update(['available_balance' => 0]);
        $withdrawal = Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 30,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Processing,
            'payout_channel' => 'junipay',
            'junipay_reference' => 'JPREF1',
            'junipay_trans_id' => 'DIS200',
        ]);

        Http::fake([self::API.'/checktranstatus' => Http::sequence()
            ->push(['status' => 'PENDING', 'info' => ['status' => 'PENDING']], 200)
            ->push(['status' => 'FAILED', 'info' => ['status' => 'FAILED']], 200)]);

        $payload = ['status' => 'failed', 'message' => 'Wallet not found', 'trans_id' => 'DIS200', 'foreignID' => 'JPREF1'];

        $this->postJson('/webhooks/junipay', $payload)->assertOk();
        $this->assertSame(WithdrawalStatus::Processing, $withdrawal->fresh()->status);

        $this->postJson('/webhooks/junipay', $payload)->assertOk();
        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->fresh()->status);
        $this->assertEqualsWithDelta(30.0, (float) $wallet->fresh()->available_balance, 0.001);
    }
}
