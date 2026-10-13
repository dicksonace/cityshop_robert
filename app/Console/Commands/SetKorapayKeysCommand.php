<?php

namespace App\Console\Commands;

use App\Services\KorapayService;
use App\Services\PlatformSettings;
use Illuminate\Console\Command;

/**
 * KORAPAY_PUBLIC_KEY='pk_live_…' KORAPAY_SECRET_KEY='sk_live_…' php artisan cityshop:set-korapay-keys
 */
class SetKorapayKeysCommand extends Command
{
    protected $signature = 'cityshop:set-korapay-keys';

    protected $description = 'Save Korapay live keys (encrypted) and turn Korapay payments on';

    public function handle(KorapayService $korapay): int
    {
        $public = trim((string) (getenv('KORAPAY_PUBLIC_KEY') ?: ''));
        $secret = trim((string) (getenv('KORAPAY_SECRET_KEY') ?: ''));

        if ($public === '') {
            $public = trim((string) $this->ask('Korapay public key (pk_live_…)'));
        }
        if ($secret === '') {
            $secret = trim((string) $this->secret('Korapay secret key (sk_live_…)'));
        }

        PlatformSettings::saveKorapayApiKeys(['public_key' => $public, 'secret_key' => $secret]);
        PlatformSettings::saveKorapayPaymentsSettings(['locked' => false]);

        $probe = $korapay->probeCredentials();
        $probe['ok'] ? $this->info($probe['message']) : $this->error('Saved, but Korapay said: '.$probe['message']);
        $this->line('Webhook URL for Korapay → Settings → API Configuration → Notification URLs: '.url('/webhooks/korapay'));

        return $probe['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
