<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\PendingCheckoutDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A checkout that cannot go through has to say why. Anything that silently
 * bounces the buyer back looks like the page froze under the Continue button.
 */
class CheckoutFailureFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_continue_to_payment_with_a_bad_coupon_reports_the_reason(): void
    {
        $this->withoutVite();
        [$buyer, $seller, $address] = $this->buyerWithCart();

        $this->actingAs($buyer)
            ->post(route('checkout.store'), [
                'address_id' => $address->id,
                'payment_method' => 'momo',
                'seller_payments' => [
                    (string) $seller->id => ['channel' => 'marketplace'],
                ],
                'seller_coupons' => [
                    (string) $seller->id => 'NOSUCHCODE',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('coupon');

        $this->assertSame(0, Order::count());
    }

    public function test_pay_step_sends_the_buyer_back_with_a_reason_when_the_draft_no_longer_adds_up(): void
    {
        $this->withoutVite();
        [$buyer, $seller, $address] = $this->buyerWithCart();

        // The coupon was fine when Continue was pressed and has since gone away.
        PendingCheckoutDraft::putForUser(
            $buyer,
            $address->id,
            $address->toShippingArray(),
            [(string) $seller->id => ['channel' => 'marketplace']],
            [(string) $seller->id => 'EXPIRED10'],
            'momo',
        );

        $this->actingAs($buyer)
            ->get(route('checkout.paystack-draft'))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error');
    }

    /**
     * @return array{0: User, 1: User, 2: BuyerAddress}
     */
    private function buyerWithCart(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Secure Shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_direct_payments' => false,
            'accept_marketplace_payments' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'electric-bike-feedback',
            'price' => 350,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Kofi',
            'last_name' => 'Amoah',
            'phone' => '0538790083',
            'address_line' => 'Sefwi Bekwai',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);

        return [$buyer, $seller, $address];
    }
}
