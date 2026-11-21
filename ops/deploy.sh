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
  # Preserve an existing checkout's runtime files; never overwrite shared data.
  if command -v rsync >/dev/null; then
    rsync -a --ignore-existing storage/ "$SHARED_DIR/storage/"
  else
    cp -an storage/. "$SHARED_DIR/storage/"
  fi
  mv storage "$SHARED_DIR/storage-scaffold-$(date -u +%Y%m%dT%H%M%SZ)-$$"
fi
ln -sfn "$SHARED_DIR/.env" .env
ln -sfn "$SHARED_DIR/storage" storage
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
php -r 'foreach (["bootstrap/cache/packages.php", "bootstrap/cache/services.php", "bootstrap/cache/config.php"] as $file) { if (is_file($file)) { unlink($file); } }'
php artisan package:discover --no-interaction --no-ansi
php artisan config:clear
php artisan portal:preflight
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo 'Release prepared. Verify document root is this release public; do not expose repository root.'
