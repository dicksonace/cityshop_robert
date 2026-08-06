<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_upload_and_remove_profile_avatar(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $upload = $this->post(
            '/api/v1/profile/avatar',
            [
                'avatar' => UploadedFile::fake()->image('kofi.jpg', 400, 400),
            ],
            ['Accept' => 'application/json'],
        );

        $upload
            ->assertOk()
            ->assertJsonPath('message', 'Profile picture updated.')
            ->assertJsonStructure(['user' => ['id', 'avatar']]);

        $avatarUrl = $upload->json('user.avatar');
        $this->assertIsString($avatarUrl);
        $this->assertStringContainsString('/storage/avatars/', $avatarUrl);

        $buyer->refresh();
        $this->assertNotNull($buyer->avatar);
        Storage::disk('public')->assertExists($buyer->avatar);

        $this->deleteJson('/api/v1/profile/avatar')
            ->assertOk()
            ->assertJsonPath('message', 'Profile picture removed.')
            ->assertJsonPath('user.avatar', null);

        $this->assertNull($buyer->fresh()->avatar);
    }

    public function test_avatar_upload_requires_an_image(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->post(
            '/api/v1/profile/avatar',
            [
                'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable();
    }
}
