#!/usr/bin/env bash
# ONE-TIME production launch helper.
# Clears all marketplace orders, sets web admin login, then locks itself forever
# (marker survives git pull / deploy). To unlock later (emergency only):
#   rm -f storage/app/.cityshop-onetime-launch-done
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"
EMAIL="${ADMIN_EMAIL:-admin@cityshop.com}"
PASSWORD="${ADMIN_PASSWORD:-Admin24@CityShop!}"
MARKER="storage/app/.cityshop-onetime-launch-done"
CONFIRM_WORD="LAUNCH-ONCE"

cd "$APP_DIR"

if [[ -f "$MARKER" ]]; then
  echo "==> BLOCKED: this one-time launch already ran."
  echo "    Marker: $APP_DIR/$MARKER"
  echo "    Ran at: $(cat "$MARKER" 2>/dev/null || echo unknown)"
  echo "    Orders clear + admin set will NOT run again."
  exit 2
fi

echo "==> ONE-TIME CityShop launch"
echo "    1) Delete ALL orders / checkouts / order wallet rows (users & products kept)"
echo "    2) Set web admin login"
echo "    URL:   https://cityunlock.net/admin24/login"
echo "    Email: $EMAIL"
echo "    After success this script will NEVER run again on this server."
echo
read -r -p "Type ${CONFIRM_WORD} to continue: " confirm
if [[ "$confirm" != "$CONFIRM_WORD" ]]; then
  echo "Aborted."
  exit 1
fi

mkdir -p storage/app

echo "==> Pull latest code"
git pull origin main

echo "==> Clear all orders"
$PHP_BIN artisan cityshop:clear-orders --force

echo "==> Set admin credentials"
$PHP_BIN artisan cityshop:set-admin --email="$EMAIL" --password="$PASSWORD" --force

echo "==> Refresh caches"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache 2>/dev/null || true

# Lock forever — written only after success so a failed run can be retried.
{
  echo "used_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "host=$(hostname 2>/dev/null || echo unknown)"
  echo "user=$(whoami 2>/dev/null || echo unknown)"
  echo "email=$EMAIL"
  echo "action=clear-orders+set-admin"
} > "$MARKER"
chmod 600 "$MARKER" 2>/dev/null || true

echo
echo "==> DONE (one-time). Locked."
echo "    Admin login: https://cityunlock.net/admin24/login"
echo "    Email:       $EMAIL"
echo "    Password:    (ADMIN_PASSWORD you used, default Admin24@CityShop!)"
echo "    Re-run will fail until marker is removed (not recommended)."
