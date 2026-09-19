<?php

/**
 * Upsert CityShop Flutterwave live keys into .env.
 *
 * Public key defaults to CITY UNLOCK VENTURES (app.flutterwave.com) V3 live.
 * Secret key is never stored in git — pass it when you run the script.
 *
 * Usage (on cityunlock.net):
 *   FLW_SECRET_KEY='FLWSECK-…-X' php scripts/apply-flutterwave-env.php "$HOME/domains/cityunlock.net/cityshop/.env"
 *
 * Optional overrides:
 *   FLW_PUBLIC_KEY=… FLW_SECRET_HASH=… php scripts/apply-flutterwave-env.php .env
 */

$envPath = $argv[1] ?? (__DIR__.'/../.env');

if (! is_file($envPath)) {
    fwrite(STDERR, "Missing .env at {$envPath}\n");
    exit(1);
}

$decode = static function (string $blob): string {
    $value = base64_decode($blob, true);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException('Invalid Flutterwave env blob.');
    }

    return $value;
};

$argValue = static function (string $name): ?string {
    global $argv;
    $prefix = '--'.$name.'=';
    foreach (array_slice($argv, 2) as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return null;
};

$cityUnlockPublic = $decode('RkxXUFVCSy1kMWE3MDU0NTRkMDVhZGMxODIxNWExMzg3MTU2NGE0Yy1Y');
$webhookHash = $decode('Q2l0eVVubG9ja0Zsd1doMjAyNg==');

$public = trim((string) ($argValue('public') ?: getenv('FLW_PUBLIC_KEY') ?: $cityUnlockPublic));
$secret = trim((string) ($argValue('secret') ?: getenv('FLW_SECRET_KEY') ?: ''));
$hash = trim((string) ($argValue('hash') ?: getenv('FLW_SECRET_HASH') ?: $webhookHash));

if ($secret === '') {
    $existing = file_get_contents($envPath) ?: '';
    if (preg_match('/^FLW_SECRET_KEY=(.+)$/m', $existing, $m) === 1) {
        $secret = trim($m[1], " \t\n\r\0\x0B\"'");
    }
}

if (str_contains(strtoupper($public), 'TEST') || str_contains(strtoupper($secret), 'TEST')) {
    fwrite(STDERR, "Refusing to write TEST Flutterwave keys to production .env.\n");
    exit(1);
}

if ($public === '' || ! str_starts_with($public, 'FLWPUBK-')) {
    fwrite(STDERR, "FLW_PUBLIC_KEY must be a live Flutterwave public key (FLWPUBK-…-X).\n");
    exit(1);
}

if ($secret === '' || ! str_starts_with($secret, 'FLWSECK-')) {
    fwrite(STDERR, "FLW_SECRET_KEY is required.\n");
    fwrite(STDERR, "Copy the CITY UNLOCK VENTURES secret from Flutterwave → Settings → API keys (reveal/copy).\n");
    fwrite(STDERR, "Then: FLW_SECRET_KEY='FLWSECK-…-X' php scripts/apply-flutterwave-env.php .env\n");
    exit(1);
}

$pairs = [
    'FLW_PUBLIC_KEY' => $public,
    'FLW_SECRET_KEY' => $secret,
    'FLW_SECRET_HASH' => $hash,
    'VITE_FLW_PUBLIC_KEY' => '${FLW_PUBLIC_KEY}',
];

$env = file_get_contents($envPath);
if ($env === false) {
    fwrite(STDERR, "Could not read {$envPath}\n");
    exit(1);
}

if (! str_ends_with($env, "\n")) {
    $env .= "\n";
}

foreach ($pairs as $key => $value) {
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    if (preg_match($pattern, $env)) {
        $env = preg_replace($pattern, $line, $env, 1);
    } else {
        $env .= $line."\n";
    }
}

if (file_put_contents($envPath, $env) === false) {
    fwrite(STDERR, "Could not write {$envPath}\n");
    exit(1);
}

echo "Applied Flutterwave live env:\n";
echo '  FLW_PUBLIC_KEY='.substr($public, 0, 18)."…\n";
echo "  FLW_SECRET_KEY=**** (set)\n";
echo '  FLW_SECRET_HASH='.$hash."\n";
echo "  VITE_FLW_PUBLIC_KEY=\${FLW_PUBLIC_KEY}\n";
echo "\nIn Flutterwave dashboard → Settings → Webhooks:\n";
echo "  URL:  https://cityunlock.net/webhooks/flutterwave\n";
echo '  Hash: '.$hash."\n";
echo "  Events: charge.completed\n";
