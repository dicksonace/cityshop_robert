#!/usr/bin/env bash
# Delete ALL marketplace orders on production (keeps users, products, wallets deposits).
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"

echo "==> WARNING: This permanently deletes ALL orders, checkouts, and order payments."
echo "==> Seller pending balances will be zeroed. Users/products/top-ups are kept."
read -r -p "Type CLEAR-ORDERS to continue: " confirm
if [[ "$confirm" != "CLEAR-ORDERS" ]]; then
  echo "Aborted."
  exit 1
fi

cd "$APP_DIR"

echo "==> Pull latest code"
git pull origin main

echo "==> Clear all orders"
$PHP_BIN artisan cityshop:clear-orders --force

echo "==> Refresh caches"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "==> Done. Admin order queues and paid revenue should now be empty."
echo "    Refresh the admin app / dashboard to confirm."
