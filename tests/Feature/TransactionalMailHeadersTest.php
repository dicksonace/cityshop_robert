<?php

namespace Tests\Feature;

use App\Listeners\AddTransactionalMailHeaders;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class TransactionalMailHeadersTest extends TestCase
{
    public function test_order_mail_gets_transactional_headers(): void
    {
        config(['app.url' => 'https://cityunlock.net', 'mail.from.address' => 'wedplanghana@scholatrade.com']);

        $message = (new Email)
            ->from('wedplanghana@scholatrade.com')
            ->to('seller@example.com')
            ->subject('New order')
            ->text('You have a new order.');

        (new AddTransactionalMailHeaders)->handle(new MessageSending($message));

        $headers = $message->getHeaders();

        $this->assertTrue($headers->has('List-Unsubscribe'));
        $this->assertStringContainsString('cityunlock.net/contact', $headers->get('List-Unsubscribe')->getBodyAsString());
        $this->assertTrue($headers->has('List-Unsubscribe-Post'));
        $this->assertSame('auto-generated', $headers->get('Auto-Submitted')->getBodyAsString());
        $this->assertTrue($headers->has('X-Auto-Response-Suppress'));
    }
}
