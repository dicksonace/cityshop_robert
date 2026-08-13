<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Notifications\AdminWalletDepositNotification;
use App\Notifications\WalletFundedNotification;
use App\Services\AdminNotifier;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminWalletAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_paystack_deposit_emails_and_sms_all_system_admins(): void
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
        Notification::assertSentTo($adminA, AdminWalletDepositNotification::class);
        Notification::assertSentTo($adminB, AdminWalletDepositNotification::class);
        Notification::assertSentTo($adminA, AdminWalletDepositNotification::class, function ($notification, $channels) {
            return $notification->amount === 80.0
                && $notification->pendingProof === false
                && $notification->reference === 'PSK-TEST-80'
                && in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });
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
}
