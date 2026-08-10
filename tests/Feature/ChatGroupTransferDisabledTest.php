<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ChatService;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatGroupTransferDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_group_chat_hides_transfer_endpoints(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $friend = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Asare Kwame']);
        $third = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        PaymentPinService::set($me, '2468');
        WalletService::ensure($me)->update(['available_balance' => 500]);

        $conversation = ChatService::createGroup($me, 'Weekend group', [$friend->id, $third->id]);

        $this->actingAs($me)
            ->getJson(route('chat.messages.transfer.meta', $conversation))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Wallet transfers are only available in 1:1 chats.');

        $this->actingAs($me)
            ->postJson(route('chat.messages.transfer', $conversation), [
                'amount' => 20,
                'payment_pin' => '2468',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Wallet transfers are only available in 1:1 chats.');
    }
}
