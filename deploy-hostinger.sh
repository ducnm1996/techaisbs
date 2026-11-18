#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT/app"

if ! command -v php >/dev/null || ! command -v composer >/dev/null; then
    echo "Can PHP >= 8.3 va Composer trong PATH tren hosting." >&2
    exit 1
fi

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || {
    echo "Hay chon PHP >= 8.3 trong hPanel va cho PHP CLI." >&2
    exit 1
}

if [[ ! -f .env ]]; then
    echo "Can tao app/.env tren hosting tu app/.env.example truoc." >&2
    echo "Dat APP_ENV=production, APP_DEBUG=false, APP_URL=https://techai.sbs," >&2
    echo "SESSION_SECURE_COOKIE=true va thong tin MySQL cua Hostinger." >&2
    exit 1
fi

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generate once. Preserve the key that protects existing sessions and data.
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
exit(empty(config("app.key")) ? 2 : 0);
' || {
    status=$?
    if [[ "$status" -eq 2 ]]; then
        php artisan key:generate --force
    else
        exit "$status"
    fi
}

php artisan migrate --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Da trien khai Mam. Kiem tra https://techai.sbs/login."
