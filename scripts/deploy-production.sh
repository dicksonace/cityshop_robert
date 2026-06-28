#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer.phar}"

echo "==> Deploying CityShop in $APP_DIR"
cd "$APP_DIR"

echo "==> Pull latest code"
git pull origin main

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

echo "==> Cache for production"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "==> Fix permissions"
chmod -R 775 storage bootstrap/cache

echo "==> Done. Open https://cityunlock.net"
