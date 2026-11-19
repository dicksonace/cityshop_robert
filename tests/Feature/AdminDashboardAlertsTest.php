<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\KycVerification;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\AdminKycSubmittedNotification;
use App\Notifications\AdminSellerApplicationNotification;
use App\Support\AdminAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function kyc(User $user, string $card): KycVerification
    {
        return KycVerification::create([
            'user_id' => $user->id,
            'ghana_card_number' => $card,
            'full_name' => $user->name,
            'front_path' => 'kyc/front/a.jpg',
            'back_path' => 'kyc/back/b.jpg',
            'status' => KycStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function test_alert_disappears_once_kyc_is_reviewed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $kyc = $this->kyc(User::factory()->create(), 'GHA-111111111-1');

        AppNotification::create([
            'user_id' => $admin->id,
            'type' => 'admin_action',
            'title' => 'Needs a decision',
            'body' => 'CityShop admin: Ama submitted Ghana Card KYC (GHA-111111111-1).',
            'data' => AdminAlerts::subjectData(new AdminKycSubmittedNotification($kyc)),
        ]);

        $this->assertCount(1, AdminAlerts::forUser($admin));

        $kyc->update(['status' => KycStatus::Approved]);

        $this->assertCount(0, AdminAlerts::forUser($admin));
    }

    public function test_old_kyc_alert_without_link_hides_after_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $kyc = $this->kyc(User::factory()->create(), 'GHA-222222222-2');

        AppNotification::create([
            'user_id' => $admin->id,
            'type' => 'admin_action',
            'title' => 'Needs a decision',
            'body' => 'CityShop admin: Kofi submitted Ghana Card KYC (GHA-222222222-2). Review in Admin.',
        ]);

        $this->assertCount(1, AdminAlerts::forUser($admin));

        $kyc->update(['status' => KycStatus::Approved]);

        $this->assertCount(0, AdminAlerts::forUser($admin));
    }

    public function test_seller_application_alert_clears_after_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Emmanuel Baidoo']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'KOBEE COMPUTER REPAIRS',
            'status' => SellerStatus::Pending,
        ]);

        AppNotification::create([
            'user_id' => $admin->id,
            'type' => 'admin_action',
            'title' => 'Needs a decision',
            'body' => 'CityShop admin: Emmanuel Baidoo applied to sell (KOBEE COMPUTER REPAIRS). Review in admin.',
            'data' => AdminAlerts::subjectData(
                new AdminSellerApplicationNotification($seller, 'KOBEE COMPUTER REPAIRS', $profile),
                $profile,
            ),
        ]);

        $this->assertCount(1, AdminAlerts::forUser($admin));
        $this->assertSame('/sellers', AdminAlerts::forUser($admin)->first()['href']);

        $profile->update(['status' => SellerStatus::Approved, 'approved_at' => now()]);

        $this->assertCount(0, AdminAlerts::forUser($admin));
    }

    public function test_old_seller_application_alert_without_link_hides_after_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'KOBEE COMPUTER REPAIRS',
            'status' => SellerStatus::Pending,
        ]);

        AppNotification::create([
            'user_id' => $admin->id,
            'type' => 'admin_action',
            'title' => 'Needs a decision',
            'body' => 'CityShop admin: Emmanuel Baidoo applied to sell (KOBEE COMPUTER REPAIRS). Review in admin.',
        ]);

        $this->assertCount(1, AdminAlerts::forUser($admin));

        $profile->update(['status' => SellerStatus::Approved, 'approved_at' => now()]);

        $this->assertCount(0, AdminAlerts::forUser($admin));
    }
}
