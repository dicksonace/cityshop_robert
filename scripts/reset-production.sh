#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"

echo "==> WARNING: This deletes ALL CityShop data on the server."
echo "==> Fresh install: admin account + categories only (no demo products)."
read -r -p "Type RESET to continue: " confirm
if [[ "$confirm" != "RESET" ]]; then
  echo "Aborted."
  exit 1
fi

cd "$APP_DIR"

echo "==> Pull latest code"
git pull origin main

echo "==> Reset database"
$PHP_BIN artisan cityshop:reset --force

echo "==> Storage link"
$PHP_BIN artisan storage:link 2>/dev/null || true

echo "==> Cache for production"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

chmod -R 775 storage bootstrap/cache

echo "==> Done."
echo "    Admin: https://cityunlock.net/admin/login"
echo "    Email: admin@cityshop.com"
echo "    Password: password (change immediately)"
