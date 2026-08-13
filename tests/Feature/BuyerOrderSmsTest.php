<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_invoice_is_email_only(): void
    {
        $buyer = User::factory()->create();

        foreach ([InvoiceType::Customer, InvoiceType::CustomerMaster] as $type) {
            $invoice = new Invoice([
                'invoice_number' => 'INVTEST1',
                'type' => $type,
                'total' => 5,
                'payment_status' => 'paid',
            ]);

            $this->assertSame(
                ['mail'],
                (new InvoiceSentNotification($invoice))->via($buyer),
                $type->value.' invoice should not SMS the buyer',
            );
        }
    }

    public function test_seller_invoice_still_emails_and_sms(): void
    {
        $seller = User::factory()->create(['mobile' => '0241112223']);
        $invoice = new Invoice([
            'invoice_number' => 'INVTEST2',
            'type' => InvoiceType::Seller,
            'total' => 5,
            'payment_status' => 'paid',
        ]);

        $this->assertSame(
            ['mail', SmsChannel::class],
            (new InvoiceSentNotification($invoice))->via($seller),
        );
    }

    public function test_buyer_and_seller_alerts_are_one_mail_and_one_sms(): void
    {
        $buyer = User::factory()->create(['mobile' => '0249998887']);
        $seller = User::factory()->create(['mobile' => '0241112223']);
        $item = new OrderItem(['product_name' => 'HP 1040G8 i5']);
        $order = new Order([
            'order_number' => 'CS202608138208B7',
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->assertSame(
            ['mail', SmsChannel::class],
            (new OrderPlacedNotification($order))->via($buyer),
        );
        $this->assertSame(
            ['mail', SmsChannel::class],
            (new PaymentConfirmedNotification($order, $item))->via($seller),
        );
    }

    public function test_order_email_says_payment_complete_not_to_confirm(): void
    {
        $paid = new Order(['payment_status' => PaymentStatus::Paid]);
        $pending = new Order(['payment_status' => PaymentStatus::Pending]);

        $this->assertSame(
            'Your order has been placed. Payment complete.',
            (new OrderPlacedNotification($paid))->buyerIntroLine(),
        );
        $this->assertSame(
            'Your order has been placed.',
            (new OrderPlacedNotification($pending))->buyerIntroLine(),
        );
        $this->assertStringNotContainsString(
            'to confirm',
            (new OrderPlacedNotification($paid))->buyerIntroLine(),
        );
    }

    public function test_seller_paid_order_email_says_payment_complete(): void
    {
        $item = new OrderItem(['product_name' => 'HP 1040G8 i5']);
        $paid = new Order([
            'order_number' => 'CS202608138208B7',
            'payment_status' => PaymentStatus::Paid,
        ]);
        $pending = new Order([
            'order_number' => 'CS202608138208B7',
            'payment_status' => PaymentStatus::Pending,
        ]);

        $paidMail = (new PaymentConfirmedNotification($paid, $item, pendingOrder: true))->sellerIntroLine();
        $pendingMail = (new PaymentConfirmedNotification($pending, $item, pendingOrder: true))->sellerIntroLine();

        $this->assertSame('You have a new order. Payment complete: HP 1040G8 i5', $paidMail);
        $this->assertStringNotContainsString('awaiting payment', $paidMail);
        $this->assertSame('You have a new order awaiting payment: HP 1040G8 i5', $pendingMail);
    }

    public function test_order_status_sms_only_for_confirm_receipt_and_cancel(): void
    {
        $buyer = User::factory()->create(['mobile' => '0249998887']);
        $item = new OrderItem(['product_name' => 'HP 1040G8 i5']);

        foreach (['packed', 'shipped', 'delivered', 'call_confirmed'] as $status) {
            $this->assertSame(
                ['mail'],
                (new OrderStatusUpdatedNotification($item, $status))->via($buyer),
                $status.' should be email only',
            );
        }

        foreach (['awaiting_confirmation', 'cancelled'] as $status) {
            $this->assertSame(
                ['mail', SmsChannel::class],
                (new OrderStatusUpdatedNotification($item, $status))->via($buyer),
                $status.' should still SMS',
            );
        }
    }
}
