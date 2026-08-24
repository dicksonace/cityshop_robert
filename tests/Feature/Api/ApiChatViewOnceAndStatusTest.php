<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiChatViewOnceAndStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_once_photo_hides_url_until_recipient_opens_it_once(): void
    {
        Storage::fake('public');

        $sender = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $recipient = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);

        Sanctum::actingAs($sender);
        $conversationId = $this->postJson('/api/v1/messages', ['user_id' => $recipient->id])
            ->assertCreated()
            ->json('conversation.id');

        $upload = $this->post('/api/v1/messages/'.$conversationId.'/image', [
            'image' => UploadedFile::fake()->image('secret.jpg'),
            'view_once' => '1',
            'caption' => 'for your eyes',
        ], ['Accept' => 'application/json']);

        $upload->assertCreated()
            ->assertJsonPath('message.view_once', true)
            ->assertJsonPath('message.view_once_opened', false)
            ->assertJsonPath('message.image_url', null)
            ->assertJsonPath('message.metadata.image_url', null);

        $messageId = $upload->json('message.id');

        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/messages/'.$conversationId)
            ->assertOk()
            ->assertJsonPath('messages.0.view_once', true)
            ->assertJsonPath('messages.0.image_url', null);

        $opened = $this->postJson('/api/v1/messages/'.$conversationId.'/messages/'.$messageId.'/view-once')
            ->assertOk();
        $this->assertNotEmpty($opened->json('image_url'));
        $opened->assertJsonPath('message.view_once_opened', true)
            ->assertJsonPath('message.image_url', null);

        $this->postJson('/api/v1/messages/'.$conversationId.'/messages/'.$messageId.'/view-once')
            ->assertStatus(422);

        $this->postJson('/api/v1/messages/'.$conversationId.'/messages/'.$messageId.'/forward', [
            'member_ids' => [$sender->id],
        ])->assertStatus(422);
    }

    public function test_buyers_and_sellers_can_post_and_view_status(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama Buyer']);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Kofi Seller']);

        Sanctum::actingAs($buyer);
        $this->post('/api/v1/status', [
            'image' => UploadedFile::fake()->image('story.jpg'),
            'body' => 'New stock',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status.type', 'image');

        Sanctum::actingAs($seller);
        $feed = $this->getJson('/api/v1/status')
            ->assertOk();
        $this->assertNotEmpty($feed->json('users'));
        $this->assertSame($buyer->id, $feed->json('users.0.user.id'));
        $statusId = $feed->json('users.0.items.0.id');
        $this->assertFalse($feed->json('users.0.items.0.viewed'));

        $this->postJson('/api/v1/status/'.$statusId.'/view')
            ->assertOk()
            ->assertJsonPath('status.viewed', true);

        $this->getJson('/api/v1/status')
            ->assertJsonPath('users.0.items.0.viewed', true);

        $this->travel(25)->hours();
        $this->getJson('/api/v1/status')
            ->assertJsonPath('users', []);
        $this->assertSame(0, UserStatus::query()->count());
    }
}
