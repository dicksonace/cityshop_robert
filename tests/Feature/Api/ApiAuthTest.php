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
        $this->assertDatabaseHas('users', [
            'email' => 'ama@example.com',
            'country' => 'Ghana',
        ]);
    }

    public function test_buyer_can_register_with_a_chosen_country(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Nana Buyer',
            'mobile' => '0530000011',
            'country' => 'Nigeria',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.country', 'Nigeria');

        $this->assertDatabaseHas('users', [
            'mobile' => '0530000011',
            'country' => 'Nigeria',
        ]);
    }

    public function test_buyer_cannot_register_with_an_unknown_country(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad Country',
            'mobile' => '0530000012',
            'country' => 'Narnia',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['country']);
    }

    public function test_buyer_can_register_without_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Email Buyer',
            'mobile' => '0530000099',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.mobile', '0530000099')
            ->assertJsonPath('user.email', null);

        $this->assertDatabaseHas('users', [
            'mobile' => '0530000099',
            'email' => null,
        ]);
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

    public function test_buyer_can_login_with_233_when_stored_as_local_zero(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'mobile' => '0248620718',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '233248620718',
            'password' => 'password',
            'portal' => 'buyer',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/v1/auth/login', [
            'login' => '+233248620718',
            'password' => 'password',
            'portal' => 'buyer',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_buyer_cannot_register_233_duplicate_of_local_mobile(): void
    {
        User::factory()->create([
            'role' => UserRole::Buyer,
            'mobile' => '0248620718',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dup Buyer',
            'mobile' => '233248620718',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile']);
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
