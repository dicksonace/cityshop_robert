<?php

/**
 * Upsert CityShop production .env keys (mail, SMS, Paystack, queue).
 * Does not touch APP_KEY, DB_*, or other existing secrets.
 *
 * Temporary mailbox: WedPlan SMTP until CityShop has its own.
 */
$envPath = $argv[1] ?? (__DIR__.'/../.env');

if (! is_file($envPath)) {
    fwrite(STDERR, "Missing .env at {$envPath}\n");
    exit(1);
}

$decode = static function (string $blob): string {
    $value = base64_decode($blob, true);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException('Invalid production env blob.');
    }

    return $value;
};

$mailPassword = $decode('dlQuZGZrS0VfJGtickxXMg==');
$formulaKey = $decode('cGtfdGVzdF82NTM0Y2ZiYzhlMmZlNGM1ZmFiZGNjYTdlNDYwYWUxNmQ1ZGJmYjYyMjk2ZTI2OGM=');
$paystackPublic = $decode('cGtfbGl2ZV83MWMxYmU4ZjE5MzQ3MzNmNjVkZTM1MDQ4MmJhYWRmODEzZTliZTM5');
$paystackSecret = $decode('c2tfbGl2ZV9jMmU3Y2U1YWQxOTYyZTk1MDFmNDE0OTdkYzg5ZDM2ZTg2NDM0YzUx');

$pairs = [
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'smtp',
    'MAIL_SCHEME' => 'smtps',
    'MAIL_HOST' => 'mail.scholatrade.com',
    'MAIL_PORT' => '465',
    'MAIL_USERNAME' => 'wedplanghana@scholatrade.com',
    'MAIL_PASSWORD' => "'{$mailPassword}'",
    'MAIL_ENCRYPTION' => '',
    'MAIL_FROM_ADDRESS' => 'wedplanghana@scholatrade.com',
    'MAIL_FROM_NAME' => '"CityShop"',
    'SMS_DRIVER' => 'formula_dc',
    'FORMULA_DC_API_KEY' => $formulaKey,
    'FORMULA_DC_SENDER' => 'Cityshop',
    'FORMULA_DC_BASE_URL' => 'https://api.formula-dc.com/api/v1/external',
    'FORMULA_DC_TEST_MODE' => 'false',
    'PAYSTACK_PUBLIC_KEY' => $paystackPublic,
    'PAYSTACK_SECRET_KEY' => $paystackSecret,
    'PAYSTACK_LOCAL_PERCENT' => '1.95',
    'PAYSTACK_LOCAL_FLAT' => '0',
    'VITE_PAYSTACK_PUBLIC_KEY' => '${PAYSTACK_PUBLIC_KEY}',
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

echo "Applied production env:\n";
echo "  QUEUE_CONNECTION=sync\n";
echo "  MAIL_MAILER=smtp (".$pairs['MAIL_HOST'].")\n";
echo "  MAIL_FROM_ADDRESS=".$pairs['MAIL_FROM_ADDRESS']."\n";
echo "  SMS_DRIVER=formula_dc sender=".$pairs['FORMULA_DC_SENDER']."\n";
echo "  PAYSTACK_PUBLIC_KEY=".substr($paystackPublic, 0, 10)."...\n";
