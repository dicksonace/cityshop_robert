<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Gmail / Yahoo treat CityShop mail as bulk unless headers look transactional.
 * Full inboxing still needs SPF/DKIM on the From domain.
 */
class AddTransactionalMailHeaders
{
    public function handle(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();
        $appUrl = rtrim((string) config('app.url'), '/');
        $from = (string) config('mail.from.address');

        if (! $headers->has('List-Unsubscribe') && $from !== '') {
            $headers->addTextHeader(
                'List-Unsubscribe',
                '<'.$appUrl.'/contact>, <mailto:'.$from.'>',
            );
        }

        if (! $headers->has('List-Unsubscribe-Post')) {
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        if (! $headers->has('Auto-Submitted')) {
            $headers->addTextHeader('Auto-Submitted', 'auto-generated');
        }

        if (! $headers->has('X-Auto-Response-Suppress')) {
            $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
        }
    }
}
