#!/usr/bin/env bash
# Write CITY UNLOCK VENTURES Flutterwave live keys into production .env and unlock collections.
#
# On cityunlock.net:
#   git pull origin main
#   FLW_SECRET_KEY='FLWSECK-…-X' bash scripts/set-live-flutterwave-env.sh
#
# Copy the secret from Flutterwave → Settings → API keys (CITY UNLOCK VENTURES, Live).
# The dashboard masks it with dots — click reveal/copy. Do not use RMB Wallet keys;
# that public key is a different Flutterwave app.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/cityunlock.net/cityshop}"
PHP_BIN="${PHP_BIN:-php}"

if [[ ! -f "$APP_DIR/.env" ]]; then
    echo "Missing $APP_DIR/.env"
    exit 1
fi

if [[ -z "${FLW_SECRET_KEY:-}" ]]; then
    if [[ -t 0 ]]; then
        echo "Paste CITY UNLOCK VENTURES Secret key (input hidden):"
        read -rs FLW_SECRET_KEY
        echo
        export FLW_SECRET_KEY
    else
        echo "Set FLW_SECRET_KEY first, e.g.:"
        echo "  FLW_SECRET_KEY='FLWSECK-…-X' bash scripts/set-live-flutterwave-env.sh"
        exit 1
    fi
fi

cd "$APP_DIR"

echo "==> Writing Flutterwave keys into $APP_DIR/.env"
$PHP_BIN scripts/apply-flutterwave-env.php "$APP_DIR/.env"

echo "==> Unlock Flutterwave for checkout + wallet recharge"
$PHP_BIN artisan cityshop:unlock-flutterwave --force

echo "==> Refresh config cache"
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan config:cache

echo "==> Done. Recharge should offer Flutterwave (Paystack stays hidden)."
echo "    Webhook URL:  https://cityunlock.net/webhooks/flutterwave"
echo "    Webhook hash: CityUnlockFlwWh2026"
echo "    Paste that hash in Flutterwave → Settings → Webhooks."
