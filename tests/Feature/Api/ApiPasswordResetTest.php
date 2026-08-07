<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_code(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'buyer@example.com',
            'mobile' => '0244111222',
            'password' => 'old-password',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => '0244111222'])
            ->assertOk()
            ->assertJsonPath('message', 'A reset code was sent to your email.')
            ->assertJsonPath('email_hint', 'b****@example.com');

        Notification::assertSentTo($user, PasswordResetCodeNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'buyer@example.com',
        ]);
    }

    public function test_reset_password_with_valid_code(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'resetme@example.com',
            'mobile' => '0244000111',
            'password' => 'old-password',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'resetme@example.com'])->assertOk();

        $notification = Notification::sent($user, PasswordResetCodeNotification::class)->first();
        $code = $notification->code;

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => 'resetme@example.com',
            'code' => $code,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password1', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'resetme@example.com',
        ]);
    }

    public function test_unknown_login_does_not_reveal_account(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('email_hint', null);

        Notification::assertNothingSent();
    }

    public function test_invalid_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'buyer2@example.com',
            'password' => 'old-password',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'buyer2@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->email,
            'code' => '000000',
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertStatus(422);
    }
}
