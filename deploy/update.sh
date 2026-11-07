#!/usr/bin/env bash
set -euo pipefail
umask 022

main() {
    local project_dir branch backup_dir stamp
    project_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
    cd -- "$project_dir"
    for command in git php composer node npm flock; do
        command -v "$command" >/dev/null || { echo "Missing command: $command" >&2; return 1; }
    done
    [[ -f api/.env ]] || { echo "Complete initial setup of api/.env first." >&2; return 1; }
    branch="$(git symbolic-ref --quiet --short HEAD)"
    [[ "$branch" == main ]] || { echo "Switch to the main branch before updating." >&2; return 1; }
    [[ -z "$(git status --porcelain)" ]] || {
        echo "Working tree contains local changes; review them before updating." >&2
        return 1
    }
    mkdir -p api/runtime
    exec 9>api/runtime/update.lock
    flock -n 9 || { echo "Another update is running." >&2; return 1; }
    backup_dir="${BACKUP_DIR:-$project_dir/api/runtime/backups}"
    stamp="$(date +%Y%m%d-%H%M%S)-$$"
    # Use the currently installed code to back up the current schema before pulling.
    php api/bin/backup-database.php "$backup_dir/pre-update-$stamp.json.gz"
    git pull --ff-only origin main
    (
        cd api
        composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
        php bin/migrate.php
    )
    (
        cd admin
        npm ci --no-audit --no-fund
        npm run build
    )
    echo "Updated to $(git rev-parse --short HEAD). Reload PHP-FPM in BaoTa if OPcache requires it."
}

main "$@"
