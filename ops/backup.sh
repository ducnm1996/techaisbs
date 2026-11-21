#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${MYSQL_BACKUP_CONFIG:?Private MySQL --defaults-extra-file with backup-reader credentials}"
: "${BACKUP_DATABASE:?Database name}"
: "${BACKUP_DIR:?Private directory outside document root and repository}"
: "${AGE_RECIPIENT:?Public age recipient; keep its private key offline}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] && (( RETENTION_DAYS >= 1 && RETENTION_DAYS <= 30 ))
[[ "$BACKUP_DATABASE" =~ ^[a-zA-Z0-9_]+$ ]]
test -d "$BACKUP_DIR"
command -v age >/dev/null
command -v mysql >/dev/null
BACKUP_DIR="$(cd "$BACKUP_DIR" && pwd)"
OUTPUT="$BACKUP_DIR/portal-$(date -u +%Y%m%dT%H%M%SZ).sql.age"
trap 'test ! -e "$OUTPUT.partial" || rm -f -- "$OUTPUT.partial"' EXIT
mapfile -t TABLES < <(mysql --defaults-extra-file="$MYSQL_BACKUP_CONFIG" --batch --skip-column-names --execute "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$BACKUP_DATABASE' AND LEFT(TABLE_NAME,7)='portal_' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
(( ${#TABLES[@]} > 0 )) || { echo 'No portal tables found; refusing an empty backup' >&2; exit 1; }
for TABLE in "${TABLES[@]}"; do
  [[ "$TABLE" =~ ^portal_[a-zA-Z0-9_]+$ ]] || { echo 'Unexpected table name' >&2; exit 1; }
done
mysqldump --defaults-extra-file="$MYSQL_BACKUP_CONFIG" --single-transaction --skip-lock-tables --skip-triggers --no-tablespaces --skip-comments "$BACKUP_DATABASE" "${TABLES[@]}" | age -r "$AGE_RECIPIENT" -o "$OUTPUT.partial"
mv -- "$OUTPUT.partial" "$OUTPUT"
# The selected directory is dedicated to this application's encrypted backups.
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'portal-*.sql.age' -mmin "+$((RETENTION_DAYS * 1440))" -delete
