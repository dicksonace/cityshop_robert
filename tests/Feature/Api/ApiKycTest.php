<?php

namespace Tests\Feature\Api;

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\KycVerification;
use App\Models\User;
use App\Notifications\AdminKycSubmittedNotification;
use App\Notifications\KycDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiKycTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_recharge_requires_approved_ghana_card(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/paystack/initialize', [
            'amount' => 50,
            'method' => 'momo',
        ])->assertForbidden()
            ->assertJsonPath('code', 'kyc_required')
            ->assertJsonPath('kyc.status', 'unverified');
    }

    public function test_checkout_paystack_does_not_require_kyc(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/kyc')
            ->assertOk()
            ->assertJsonPath('data.status', 'unverified')
            ->assertJsonPath('data.can_store_funds', false);
    }

    public function test_user_can_submit_ghana_card_and_admin_can_approve(): void
    {
        Storage::fake('public');
        Notification::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama Buyer']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($buyer);

        $this->post('/api/v1/kyc', [
            'ghana_card_number' => 'GHA-123456789-1',
            'full_name' => 'Ama Buyer',
            'front' => UploadedFile::fake()->image('front.jpg'),
            'back' => UploadedFile::fake()->image('back.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_store_funds', false);

        $this->assertDatabaseHas('kyc_verifications', [
            'user_id' => $buyer->id,
            'status' => 'pending',
            'ghana_card_number' => 'GHA-123456789-1',
        ]);

        Notification::assertSentTo($admin, AdminKycSubmittedNotification::class);

        $kyc = KycVerification::firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/kyc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $kyc->id);

        $this->postJson("/api/v1/admin/kyc/{$kyc->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        Notification::assertSentTo($buyer->fresh(), KycDecisionNotification::class);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/kyc')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.can_store_funds', true);
    }

    public function test_admin_can_reject_or_ask_for_better_photos(): void
    {
        Storage::fake('public');
        Notification::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $kyc = KycVerification::create([
            'user_id' => $buyer->id,
            'ghana_card_number' => 'GHA-987654321-0',
            'full_name' => $buyer->name,
            'front_path' => 'kyc/front/a.jpg',
            'back_path' => 'kyc/back/b.jpg',
            'status' => KycStatus::Pending,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/kyc/{$kyc->id}/request-changes", [
            'admin_notes' => 'The card number is blurry. Retake the front photo.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'needs_improvement');

        $this->postJson("/api/v1/admin/kyc/{$kyc->id}/reject", [
            'admin_notes' => 'This does not look like a Ghana Card.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
