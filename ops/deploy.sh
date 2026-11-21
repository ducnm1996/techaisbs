#!/usr/bin/env bash
set -euo pipefail
# Run after checking out this release. Configure Hostinger document root separately.
: "${SHARED_DIR:?Set SHARED_DIR to a private absolute directory outside every Git checkout}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SHARED_DIR="$(cd "$SHARED_DIR" && pwd)"
case "$SHARED_DIR/" in "$APP_DIR/"*) echo 'Shared directory must be outside the checkout' >&2; exit 1;; esac
test -f "$SHARED_DIR/.env"
test -d "$SHARED_DIR/storage"
cd "$APP_DIR"
if test -e .env && ! test -L .env; then echo 'Move .env into SHARED_DIR before deployment' >&2; exit 1; fi
if test -d storage && ! test -L storage; then
  if find storage -type f ! -name '.gitignore' -print -quit | grep -q .; then echo 'Move existing private storage to SHARED_DIR first' >&2; exit 1; fi
  mv storage storage.empty-scaffold
fi
ln -sfn "$SHARED_DIR/.env" .env
ln -sfn "$SHARED_DIR/storage" storage
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan config:clear
php artisan portal:preflight
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo 'Release prepared. Verify document root is this release public; do not expose repository root.'
