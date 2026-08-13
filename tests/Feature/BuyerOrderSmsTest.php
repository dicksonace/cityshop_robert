<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\OrderPlacedNotification;
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

    public function test_buyer_still_has_order_and_payment_sms(): void
    {
        $buyer = User::factory()->create(['mobile' => '0249998887']);

        $this->assertContains(SmsChannel::class, (new OrderPlacedNotification(new \App\Models\Order))->via($buyer));
        $this->assertContains(SmsChannel::class, (new PaymentConfirmedNotification(new \App\Models\Order))->via($buyer));
    }
}
