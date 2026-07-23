<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('version', 'v1');
    }

    public function test_buyer_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ama Buyer',
            'mobile' => '0530000001',
            'email' => 'ama@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email', 'role']]);

        $this->assertSame('buyer', $response->json('user.role'));
        $this->assertDatabaseHas('users', ['email' => 'ama@example.com']);
    }

    public function test_buyer_can_login_and_access_me(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'kofi@example.com',
            'mobile' => '0530000002',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'kofi@example.com',
            'password' => 'password',
            'portal' => 'buyer',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_products_list_is_public(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
    }

    public function test_cart_requires_auth(): void
    {
        $this->getJson('/api/v1/cart')->assertUnauthorized();
    }

    public function test_authenticated_buyer_can_view_empty_cart(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('subtotal', 0);
    }
}
