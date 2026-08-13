#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer.phar}"

echo "==> Deploying CityShop in $APP_DIR"
cd "$APP_DIR"

echo "==> Pull latest code"
git pull origin main

echo "==> Apply production .env (mail, SMS, live Paystack, sync queue)"
$PHP_BIN scripts/apply-production-env.php "$APP_DIR/.env"

echo "==> Install PHP dependencies"
if [[ -f "$COMPOSER_BIN" ]]; then
    $PHP_BIN -d memory_limit=-1 "$COMPOSER_BIN" install --no-dev --optimize-autoloader
elif command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader
else
    echo "Composer not found. Run: curl -sS https://getcomposer.org/installer | $PHP_BIN"
    exit 1
fi

echo "==> Run migrations"
$PHP_BIN artisan migrate --force

echo "==> Index product images for visual search"
$PHP_BIN artisan products:index-image-colors

echo "==> Storage link (safe if already exists)"
$PHP_BIN artisan storage:link 2>/dev/null || true

echo "==> Refresh config (so .env changes actually apply)"
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

if grep -qE '^PAYSTACK_PUBLIC_KEY=pk_test_' .env 2>/dev/null; then
    echo "WARNING: PAYSTACK_PUBLIC_KEY is still a TEST key."
fi
if grep -qE '^MAIL_MAILER=log' .env 2>/dev/null; then
    echo "WARNING: MAIL_MAILER=log — emails will not leave the server."
fi
if grep -qE '^SMS_DRIVER=log' .env 2>/dev/null; then
    echo "WARNING: SMS_DRIVER=log — SMS will only write to laravel.log."
fi
if grep -qE '^QUEUE_CONNECTION=database' .env 2>/dev/null; then
    echo "WARNING: QUEUE_CONNECTION=database with no worker — queued mail/SMS will not send."
fi

echo "==> Fix permissions"
chmod -R 775 storage bootstrap/cache

echo "==> Done. Open https://cityunlock.net"
echo "    Test: php artisan mail:test you@gmail.com"
echo "          php artisan sms:send 0532700209 \"CityShop test\""
