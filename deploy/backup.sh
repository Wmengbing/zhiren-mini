#!/usr/bin/env bash
set -euo pipefail
umask 077
PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-/www/backup/zhiren-mini}"
PHP_BIN="${PHP_BIN:-php}"
mkdir -p -- "$BACKUP_DIR"
BACKUP_DIR="$(cd -- "$BACKUP_DIR" && pwd)"
case "$BACKUP_DIR/" in
  "$PROJECT_DIR/api/public/"*) echo "Backup directory must be outside the public directory" >&2; exit 1 ;;
esac
STAMP="$(date +%Y%m%d-%H%M%S)-$$"
"$PHP_BIN" "$PROJECT_DIR/api/bin/backup-database.php" "$BACKUP_DIR/database-$STAMP.json.gz"
tar -czf "$BACKUP_DIR/uploads-$STAMP.tar.gz" -C "$PROJECT_DIR/api/public" uploads
echo "Database and uploads backed up to $BACKUP_DIR"
