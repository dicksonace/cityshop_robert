<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\SellerActivationDueNotification;
use App\Notifications\SellerActivationPaidNotification;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SellerActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unprompted_seller_products_stay_visible(): void
    {
        [$seller, $profile, $product] = $this->approvedSellerWithProduct();

        $this->assertTrue($profile->isServiceActive());
        $this->assertFalse($profile->needsActivationPayment());
        $this->assertTrue($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());
        $this->assertTrue(Product::visibleInShop()->whereKey($product->id)->exists());
    }

    public function test_admin_prompt_hides_products_and_blocks_posting_but_wallet_stays_open(): void
    {
        Notification::fake();

        [$seller, $profile, $product] = $this->approvedSellerWithProduct();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 150])
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertSame('150.00', (string) $profile->activation_fee_amount);
        $this->assertNotNull($profile->activation_prompted_at);
        $this->assertFalse($profile->isServiceActive());
        $this->assertTrue($profile->needsActivationPayment());
        $this->assertFalse($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());
        $this->assertFalse(Product::visibleInShop()->whereKey($product->id)->exists());

        Notification::assertSentTo($seller, SellerActivationDueNotification::class);

        $this->actingAs($seller)
            ->get(route('seller.products.create'))
            ->assertRedirect(route('seller.activation.show'));

        $this->actingAs($seller)
            ->get(route('seller.wallet'))
            ->assertOk();

        $this->get(route('store.show', $profile->slug))->assertNotFound();
    }

    public function test_seller_pays_service_fee_from_wallet_and_store_goes_live_for_a_year(): void
    {
        Notification::fake();

        [$seller, $profile, $product] = $this->approvedSellerWithProduct();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 100]);

        PaymentPinService::set($seller, '2468');
        WalletService::ensure($seller)->update(['available_balance' => 250]);

        $this->actingAs($seller->fresh())
            ->post(route('seller.activation.pay'), ['payment_pin' => '2468'])
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->isServiceActive());
        $this->assertFalse($profile->needsActivationPayment());
        $this->assertTrue($profile->activation_paid_until?->isFuture());
        $this->assertTrue($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());

        $this->assertEqualsWithDelta(150, (float) Wallet::query()->where('user_id', $seller->id)->value('available_balance'), 0.01);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $seller->id,
            'type' => WalletTransactionType::ServiceFee->value,
            'amount' => -100,
        ]);

        Notification::assertSentTo($seller->fresh(), SellerActivationPaidNotification::class);

        $this->actingAs($seller->fresh())
            ->get(route('seller.products.create'))
            ->assertOk();
    }

    public function test_admin_can_waive_activation_without_charging_wallet(): void
    {
        [$seller, $profile, $product] = $this->approvedSellerWithProduct();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 80]);

        WalletService::ensure($seller)->update(['available_balance' => 40]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.waive', $profile), ['amount' => 80])
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->isServiceActive());
        $this->assertTrue($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());
        $this->assertEqualsWithDelta(40, (float) Wallet::query()->where('user_id', $seller->id)->value('available_balance'), 0.01);
    }

    public function test_admin_can_end_activation_so_payment_is_due_again(): void
    {
        Notification::fake();

        [, $profile, $product] = $this->approvedSellerWithProduct();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 90]);
        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.waive', $profile));

        $this->assertTrue($profile->fresh()->isServiceActive());

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.end', $profile))
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertFalse($profile->isServiceActive());
        $this->assertFalse($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());
    }

    public function test_prompting_an_already_active_seller_hides_products_again(): void
    {
        Notification::fake();

        [, $profile, $product] = $this->approvedSellerWithProduct();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 90]);
        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.waive', $profile));

        $this->assertTrue($profile->fresh()->isServiceActive());
        $this->assertTrue($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());

        $this->actingAs($admin)
            ->post(route('admin.sellers.activation.prompt', $profile), ['amount' => 120])
            ->assertRedirect();

        $profile->refresh();
        $this->assertFalse($profile->isServiceActive());
        $this->assertTrue($profile->needsActivationPayment());
        $this->assertFalse($product->fresh()->load('seller.sellerProfile')->isVisibleInShop());
        Notification::assertSentTo($profile->user, SellerActivationDueNotification::class);
    }

    /**
     * @return array{0: User, 1: SellerProfile, 2: Product}
     */
    private function approvedSellerWithProduct(): array
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'mobile' => '0532700209',
        ]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Activation Store',
            'slug' => 'activation-store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_marketplace_payments' => true,
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Activation Phone',
            'slug' => 'activation-phone',
            'price' => 200,
            'quantity' => 4,
            'status' => ProductStatus::Approved,
        ]);

        return [$seller, $profile, $product];
    }
}
