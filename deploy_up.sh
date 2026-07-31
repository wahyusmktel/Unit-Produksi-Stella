#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/var/www/Unit-Produksi-Stella"
PHP="/usr/bin/php"
COMPOSER="/usr/local/bin/composer"
NPM="/usr/bin/npm"

cd "$APP_DIR"

finish() {
    "$PHP" artisan up >/dev/null 2>&1 || true
}

trap finish EXIT

echo "==> Enable maintenance mode"
"$PHP" artisan down --retry=30 || true

echo "==> Pull latest code"
git pull --ff-only origin main

echo "==> Install PHP dependencies"
"$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Run database migrations"
"$PHP" artisan migrate --force

echo "==> Build frontend"
"$NPM" ci
"$NPM" run build

echo "==> Prepare public storage"
"$PHP" artisan storage:link || true

echo "==> Rebuild application caches"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "==> Deploy finished successfully"
