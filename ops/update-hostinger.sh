#!/usr/bin/env bash
set -euo pipefail
: "${SHARED_DIR:?Set SHARED_DIR to the existing private configuration directory}"
: "${WEB_ROOT:?Set WEB_ROOT to the domain public_html symlink}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
test -L "$WEB_ROOT"
test "$(readlink -f "$WEB_ROOT")" = "$APP_DIR/public"
test -f "$SHARED_DIR/.env"
if test -e "$APP_DIR/public/composer.json" || test -e "$APP_DIR/public/artisan" || test -e "$APP_DIR/public/.git"; then
  echo 'Repository files were copied into public. Disable the legacy hosting deploy and restore public before continuing.' >&2
  exit 1
fi
cd "$APP_DIR"
git fetch --quiet origin main
git merge --ff-only origin/main
SHARED_DIR="$SHARED_DIR" bash "$APP_DIR/ops/deploy.sh"
echo 'Updated private checkout; verify HTTPS login and blocked sensitive paths.'
