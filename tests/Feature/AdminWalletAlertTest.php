<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Models\Withdrawal;
use App\Notifications\AdminWalletDepositNotification;
use App\Notifications\AdminWithdrawalRequestedNotification;
use App\Notifications\WalletFundedNotification;
use App\Services\AdminNotifier;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminWalletAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_paystack_deposit_does_not_alert_admins(): void
    {
        Notification::fake();

        $adminA = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $adminB = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin Two']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi Amoah']);
        WalletService::ensure($buyer);

        $this->assertTrue(WalletService::creditFromVerifiedTopUp(
            $buyer->id,
            80,
            'PSK-TEST-80',
            'paystack',
        ));

        Notification::assertSentTo($buyer, WalletFundedNotification::class);
        Notification::assertNotSentTo($adminA, AdminWalletDepositNotification::class);
        Notification::assertNotSentTo($adminB, AdminWalletDepositNotification::class);
    }

    public function test_momo_paystack_deposit_does_not_sms_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi Amoah']);
        WalletService::ensure($buyer);

        $this->assertTrue(WalletService::creditFromVerifiedTopUp(
            $buyer->id,
            10,
            'TOP-6A8086D89D973',
            'momo',
        ));

        Notification::assertSentTo($buyer, WalletFundedNotification::class);
        Notification::assertNotSentTo($admin, AdminWalletDepositNotification::class);
    }

    public function test_admin_manual_credit_does_not_alert_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        WalletService::ensure($buyer);

        $this->assertTrue(WalletService::creditFromVerifiedTopUp(
            $buyer->id,
            50,
            'ADMIN-CREDIT-50',
            'admin',
        ));

        Notification::assertSentTo($buyer, WalletFundedNotification::class);
        Notification::assertNotSentTo($admin, AdminWalletDepositNotification::class);
    }

    public function test_manual_deposit_approval_does_not_sms_admins_again(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi Amoah']);
        WalletService::ensure($buyer);

        $this->assertTrue(WalletService::creditFromVerifiedTopUp(
            $buyer->id,
            20,
            'MANUAL-4-proof',
            'manual',
        ));

        Notification::assertSentTo($buyer, WalletFundedNotification::class);
        Notification::assertNotSentTo($admin, AdminWalletDepositNotification::class);
    }

    public function test_manual_deposit_proof_alerts_all_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama Buyer']);
        $topUp = WalletTopUpRequest::create([
            'user_id' => $buyer->id,
            'amount' => 120,
            'payment_reference' => 'MTN-9988',
            'network' => 'mtn',
            'proof_path' => 'wallet-top-up-proofs/demo.jpg',
            'status' => WalletTopUpStatus::Pending,
        ]);

        AdminNotifier::depositProof($buyer, $topUp);

        Notification::assertSentTo($admin, AdminWalletDepositNotification::class, function ($notification, $channels) {
            return $notification->pendingProof === true
                && $notification->amount === 120.0
                && $notification->reference === 'MTN-9988'
                && in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });
    }

    public function test_wallet_funded_sms_includes_available_balance_and_date(): void
    {
        $buyer = User::factory()->create(['mobile' => '0248620718']);
        $at = Carbon::parse('2026-08-13 15:49:00', 'Africa/Accra');

        $sms = (new WalletFundedNotification(10, 'paystack', 'TOP-6A7DE77468CE0', 2303.50, $at))->toSms($buyer);

        $this->assertStringContainsString('GH₵10.00 credited to your wallet.', $sms);
        $this->assertStringContainsString('Available Balance: GHS 2303.50', $sms);
        $this->assertStringContainsString('Ref: TOP-6A7DE77468CE0.', $sms);
        $this->assertStringContainsString('Date: 13 Aug 2026, 3:49 PM.', $sms);
    }

    public function test_withdrawal_request_emails_and_sms_all_system_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi Amoah']);
        $withdrawal = new Withdrawal([
            'amount' => 75,
            'momo_number' => '0244111222',
            'payout_channel' => 'momo',
        ]);
        $withdrawal->setRelation('user', $buyer);
        $withdrawal->created_at = Carbon::parse('2026-08-14 11:13:00', 'Africa/Accra');

        AdminNotifier::notify(new AdminWithdrawalRequestedNotification($withdrawal));

        Notification::assertSentTo($admin, AdminWithdrawalRequestedNotification::class, function ($notification, $channels) {
            return in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });

        $sms = (new AdminWithdrawalRequestedNotification($withdrawal))->toSms($admin);
        $this->assertStringContainsString('Kofi Amoah requested a GH₵75.00 MoMo withdrawal', $sms);
        $this->assertStringContainsString('Review in admin', $sms);
    }

    public function test_ghana_card_kyc_submission_sms_all_system_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'name' => 'Super Admin',
            'mobile' => '0244000000',
        ]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Asare Kwame']);
        $kyc = new \App\Models\KycVerification([
            'ghana_card_number' => 'GHA-737743882-3',
            'full_name' => 'Asare Kwame',
            'status' => 'pending',
        ]);
        $kyc->setRelation('user', $buyer);
        $kyc->submitted_at = Carbon::parse('2026-08-20 12:00:00', 'Africa/Accra');

        AdminNotifier::notify(new \App\Notifications\AdminKycSubmittedNotification($kyc));

        Notification::assertSentTo($admin, \App\Notifications\AdminKycSubmittedNotification::class, function ($notification, $channels) {
            return in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });

        $sms = (new \App\Notifications\AdminKycSubmittedNotification($kyc))->toSms($admin);
        $this->assertStringContainsString('Asare Kwame submitted Ghana Card KYC', $sms);
        $this->assertStringContainsString('GHA-737743882-3', $sms);
        $this->assertStringContainsString('Ghana Card KYC', $sms);
    }
}
