#!/usr/bin/env bash
# Update production web admin URL is already /admin24 after deploy.
# This script sets the admin email/password on the live database.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"

EMAIL="${ADMIN_EMAIL:-admin@cityshop.com}"
PASSWORD="${ADMIN_PASSWORD:-Admin24@CityShop!}"

echo "==> Setting CityShop web admin credentials"
echo "    URL:  https://cityunlock.net/admin24/login"
echo "    Email: $EMAIL"
read -r -p "Type SET-ADMIN to continue: " confirm
if [[ "$confirm" != "SET-ADMIN" ]]; then
  echo "Aborted."
  exit 1
fi

cd "$APP_DIR"
git pull origin main

$PHP_BIN artisan cityshop:set-admin --email="$EMAIL" --password="$PASSWORD" --force

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache

echo "==> Done."
echo "    Login: https://cityunlock.net/admin24/login"
echo "    Email: $EMAIL"
echo "    Password: (the ADMIN_PASSWORD you set, default Admin24@CityShop!)"
echo "    Change again anytime:"
echo "    ADMIN_EMAIL=you@email.com ADMIN_PASSWORD='YourStrongPass' bash scripts/set-admin-production.sh"
