<?php

namespace App\Console\Commands;

use App\Services\JunipayService;
use App\Services\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * php artisan cityshop:set-junipay-keys --client-id=XXXX --key-file=/tmp/junipay.key
 */
class SetJunipayKeysCommand extends Command
{
    protected $signature = 'cityshop:set-junipay-keys
        {--client-id= : Junipay Client ID}
        {--key-file= : Path to the RSA private key file (.key / .pem)}
        {--sandbox : Use the Junipay sandbox instead of live}';

    protected $description = 'Save Junipay Client ID and RSA private key (encrypted) and test them';

    public function handle(JunipayService $junipay): int
    {
        $clientId = trim((string) ($this->option('client-id') ?: getenv('JUNIPAY_CLIENT_ID') ?: ''));
        if ($clientId === '') {
            $clientId = trim((string) $this->ask('Junipay Client ID'));
        }

        $keyFile = trim((string) ($this->option('key-file') ?: ''));
        if ($keyFile === '') {
            $keyFile = trim((string) $this->ask('Path to the Junipay private key file'));
        }
        if (! is_file($keyFile) || ! is_readable($keyFile)) {
            $this->error("Cannot read the key file: {$keyFile}");

            return self::FAILURE;
        }

        try {
            PlatformSettings::saveJunipayKeys([
                'client_id' => $clientId,
                'private_key' => (string) file_get_contents($keyFile),
            ]);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?? 'Invalid private key.');

            return self::FAILURE;
        }

        PlatformSettings::saveJunipaySettings(['sandbox' => (bool) $this->option('sandbox')]);

        $this->info('Junipay keys saved (private key encrypted in the database).');
        $this->line('You can now delete the key file from the server: rm '.escapeshellarg($keyFile));

        $probe = $junipay->probeCredentials();
        $probe['ok'] ? $this->info($probe['message']) : $this->warn('Junipay said: '.$probe['message']);
        $this->line('Callback URL for Junipay: '.url('/webhooks/junipay'));

        return self::SUCCESS;
    }
}
