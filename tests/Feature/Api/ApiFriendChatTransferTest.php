<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\WalletTransferReceivedNotification;
use App\Services\PaymentPinService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiFriendChatTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_lookup_another_user_by_mobile(): void
    {
        $me = User::factory()->create([
            'role' => UserRole::Buyer,
            'mobile' => '0244000001',
        ]);
        $friend = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Ama Friend',
            'mobile' => '0244000002',
        ]);

        Sanctum::actingAs($me);

        $this->getJson('/api/v1/users/lookup?mobile=233244000002')
            ->assertOk()
            ->assertJsonPath('user.id', $friend->id)
            ->assertJsonPath('user.name', 'Ama Friend');
    }

    public function test_buyer_can_open_friend_chat_by_user_id(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer]);
        $friend = User::factory()->create(['role' => UserRole::Buyer]);

        Sanctum::actingAs($me);

        $this->postJson('/api/v1/messages', [
            'user_id' => $friend->id,
        ])
            ->assertCreated()
            ->assertJsonPath('conversation.other.id', $friend->id);
    }

    public function test_buyer_can_transfer_ghs_in_chat(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $friend = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        PaymentPinService::set($me, '2468');

        Wallet::create([
            'user_id' => $me->id,
            'available_balance' => 100,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);
        Wallet::create([
            'user_id' => $friend->id,
            'available_balance' => 5,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Sanctum::actingAs($me);

        $conversationId = $this->postJson('/api/v1/messages', [
            'user_id' => $friend->id,
        ])->json('conversation.id');

        $response = $this->postJson("/api/v1/messages/{$conversationId}/transfer", [
            'amount' => 25.5,
            'note' => 'Chop money',
            'payment_pin' => '2468',
        ])->assertCreated();

        $response
            ->assertJsonPath('message.type', MessageType::Transfer->value)
            ->assertJsonPath('message.transfer.currency', 'GHS')
            ->assertJsonPath('message.transfer.amount', 25.5)
            ->assertJsonPath('wallet.available_balance', 74.5);

        $this->assertEquals(74.5, (float) Wallet::where('user_id', $me->id)->value('available_balance'));
        $this->assertEquals(30.5, (float) Wallet::where('user_id', $friend->id)->value('available_balance'));

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $me->id,
            'type' => WalletTransactionType::TransferOut->value,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $friend->id,
            'type' => WalletTransactionType::TransferIn->value,
        ]);

        $this->assertTrue(
            Message::query()
                ->where('conversation_id', $conversationId)
                ->where('type', MessageType::Transfer)
                ->exists()
        );
    }

    public function test_transfer_received_sms_and_email_include_available_balance_and_date(): void
    {
        $sender = User::factory()->create(['name' => 'Kofi amoah']);
        $recipient = User::factory()->create(['mobile' => '0248620718']);
        $at = Carbon::parse('2026-08-13 17:12:00', 'Africa/Accra');

        $notification = new WalletTransferReceivedNotification(
            $sender,
            20,
            'TRF-E774EF3F9AAC',
            null,
            6445,
            $at,
        );

        $sms = $notification->toSms($recipient);
        $this->assertStringContainsString('You Received Ghc20.00 From Kofi amoah', $sms);
        $this->assertStringContainsString('Available Balance: GHS 6445.00', $sms);
        $this->assertStringContainsString('Ref: TRF-E774EF3F9AAC.', $sms);
        $this->assertStringContainsString('Date: 13 Aug 2026, 5:12 PM.', $sms);

        $mail = $notification->toMail($recipient);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $rendered = implode("\n", $mail->introLines);
        $this->assertStringContainsString('You received GH₵20.00 from Kofi amoah on CityShop.', $rendered);
        $this->assertStringContainsString('Available Balance: GHS 6445.00', $rendered);
        $this->assertStringContainsString('Date: 13 Aug 2026, 5:12 PM', $rendered);
    }

    public function test_transfer_requires_payment_pin(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer]);
        $friend = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($me, '2468');
        Wallet::create([
            'user_id' => $me->id,
            'available_balance' => 50,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Sanctum::actingAs($me);
        $conversationId = $this->postJson('/api/v1/messages', ['user_id' => $friend->id])
            ->json('conversation.id');

        $this->postJson("/api/v1/messages/{$conversationId}/transfer", [
            'amount' => 10,
        ])->assertUnprocessable();

        $this->postJson("/api/v1/messages/{$conversationId}/transfer", [
            'amount' => 10,
            'payment_pin' => '0000',
        ])->assertUnprocessable();
    }

    public function test_transfer_requires_sufficient_balance(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer]);
        $friend = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($me, '2468');
        Wallet::create([
            'user_id' => $me->id,
            'available_balance' => 2,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Sanctum::actingAs($me);
        $conversationId = $this->postJson('/api/v1/messages', ['user_id' => $friend->id])
            ->json('conversation.id');

        $this->postJson("/api/v1/messages/{$conversationId}/transfer", [
            'amount' => 10,
            'payment_pin' => '2468',
        ])->assertUnprocessable();
    }

    public function test_group_chats_reject_wallet_transfers(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $friend = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Asare Kwame', 'mobile' => '0202105124']);
        $third = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        PaymentPinService::set($me, '2468');

        Wallet::create([
            'user_id' => $me->id,
            'available_balance' => 1000,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Sanctum::actingAs($me);

        $conversationId = $this->postJson('/api/v1/messages/groups', [
            'name' => 'Weekend group',
            'member_ids' => [$friend->id, $third->id],
        ])->assertCreated()->json('conversation.id');

        $this->postJson("/api/v1/messages/{$conversationId}/transfer", [
            'amount' => 25,
            'payment_pin' => '2468',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Wallet transfers are only available in 1:1 chats.');

        $this->assertSame(0, Message::where('conversation_id', $conversationId)->where('type', MessageType::Transfer)->count());
        $this->assertSame(1000.0, (float) Wallet::where('user_id', $me->id)->value('available_balance'));
    }
}
