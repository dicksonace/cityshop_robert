#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER="${COMPOSER:-$PHP_BIN composer.phar}"

echo "==> Deploying CityShop in $APP_DIR"
cd "$APP_DIR"

echo "==> Pull latest code"
git pull origin main

echo "==> Install PHP dependencies"
$PHP_BIN -d memory_limit=-1 "$COMPOSER" install --no-dev --optimize-autoloader

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
