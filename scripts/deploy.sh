#!/usr/bin/env bash
# Deploys CareNexa360 to the production VPS (carenexa360.co.uk).
#
# Run from the repo root, on the machine that has Node (to build the
# frontend) and a working `ssh root@$REMOTE_HOST` — the server itself has no
# Node, so the SPA is always built locally and shipped as static files.
#
# Usage:
#   scripts/deploy.sh                 deploy both api and web (default)
#   scripts/deploy.sh --api-only      deploy only the Laravel API
#   scripts/deploy.sh --web-only      deploy only the React frontend
#   scripts/deploy.sh --allow-dirty   skip the clean-working-tree check
#   scripts/deploy.sh rollback        restore the most recent backup (both)
#   scripts/deploy.sh rollback api    restore only the api backup
#   scripts/deploy.sh rollback web    restore only the web backup
#
# How a deploy works: each release is extracted into a fresh <name>.new
# directory on the server, fully built and cache-warmed there, and only then
# swapped in with `mv` — so the live site is never mid-update. The directory
# it replaces is kept as <name>.replaced-<timestamp> instead of being
# deleted, which is what `rollback` restores; only backups beyond
# KEEP_BACKUPS are pruned.

set -euo pipefail

REMOTE_HOST="root@187.7.20.140"
REMOTE_BASE="/var/www/carenexa360"
HEALTH_URL="https://carenexa360.co.uk/up"
PHP_FPM_SERVICE="php8.5-fpm"
KEEP_BACKUPS=5

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TS="$(date -u +%Y%m%d-%H%M%S)"

DEPLOY_WEB=1
DEPLOY_API=1
ALLOW_DIRTY=0
MODE="deploy"
ROLLBACK_TARGET="both"

for arg in "$@"; do
  case "$arg" in
    --web-only) DEPLOY_API=0 ;;
    --api-only) DEPLOY_WEB=0 ;;
    --allow-dirty) ALLOW_DIRTY=1 ;;
    rollback) MODE="rollback" ;;
    api) ROLLBACK_TARGET="api" ;;
    web) ROLLBACK_TARGET="web" ;;
    -h|--help) sed -n '2,22p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'; exit 0 ;;
    *) echo "Unknown argument: $arg" >&2; exit 1 ;;
  esac
done

remote() { ssh "$REMOTE_HOST" "$@"; }

cleanup_on_failure() {
  local status=$?
  if [ $status -ne 0 ]; then
    echo "Deploy failed — cleaning up any half-built release dirs on the server." >&2
    remote "rm -rf '$REMOTE_BASE/api.new' '$REMOTE_BASE/web.new'" || true
  fi
  exit $status
}
trap cleanup_on_failure EXIT

require_clean_tree() {
  if [ "$ALLOW_DIRTY" -eq 1 ]; then
    return
  fi
  if [ -n "$(git -C "$REPO_ROOT" status --porcelain)" ]; then
    echo "Working tree has uncommitted changes — commit/stash first, or pass --allow-dirty." >&2
    exit 1
  fi
}

prune_backups() {
  local name="$1"
  remote "ls -1d '$REMOTE_BASE/${name}.replaced-'* 2>/dev/null | sort | head -n -${KEEP_BACKUPS} | xargs -r rm -rf"
}

deploy_web() {
  echo "==> Building frontend"
  (cd "$REPO_ROOT/web" && npm ci && npm run build)
  test -f "$REPO_ROOT/web/dist/index.html" || { echo "web/dist/index.html missing after build" >&2; exit 1; }

  echo "==> Shipping frontend build to server"
  remote "rm -rf '$REMOTE_BASE/web.new' && mkdir -p '$REMOTE_BASE/web.new'"
  tar -C "$REPO_ROOT/web/dist" -czf - . | remote "tar -xzf - -C '$REMOTE_BASE/web.new'"
  remote "chown -R www-data:www-data '$REMOTE_BASE/web.new'"

  echo "==> Swapping in new frontend"
  remote "mv '$REMOTE_BASE/web' '$REMOTE_BASE/web.replaced-$TS' && mv '$REMOTE_BASE/web.new' '$REMOTE_BASE/web'"
  prune_backups web
}

deploy_api() {
  echo "==> Archiving api source at $(git -C "$REPO_ROOT/api" rev-parse --short HEAD)"
  remote "rm -rf '$REMOTE_BASE/api.new' && mkdir -p '$REMOTE_BASE/api.new'"
  git -C "$REPO_ROOT/api" archive HEAD | remote "tar -x -C '$REMOTE_BASE/api.new'"

  echo "==> Carrying over .env and storage/ (uploads, logs — never rebuilt)"
  remote "cp '$REMOTE_BASE/api/.env' '$REMOTE_BASE/api.new/.env'"
  remote "rm -rf '$REMOTE_BASE/api.new/storage' && cp -a '$REMOTE_BASE/api/storage' '$REMOTE_BASE/api.new/storage'"

  echo "==> composer install (on the server, so it matches its PHP/extensions)"
  remote "cd '$REMOTE_BASE/api.new' && composer install --no-dev --optimize-autoloader --no-interaction"

  # Migrating here (still at api.new, not yet live) means a bad migration
  # aborts the whole deploy — the old code keeps serving off the old schema
  # instead of a broken migration going live.
  echo "==> Migrating"
  remote "cd '$REMOTE_BASE/api.new' && php artisan migrate --force"

  echo "==> Swapping in new api"
  remote "mv '$REMOTE_BASE/api' '$REMOTE_BASE/api.replaced-$TS' && mv '$REMOTE_BASE/api.new' '$REMOTE_BASE/api'"

  # Only now, from the api/ path this release will actually run at —
  # config:cache/route:cache bake resolved absolute paths (e.g. config/view.php's
  # realpath(storage_path(...))) into bootstrap/cache/*.php. Caching them while
  # still at api.new left the health-check view (and anything else touching
  # those cached paths) pointing at a directory that stopped existing the
  # moment the mv above ran — that's what took prod's /up down on the first
  # real deploy through this script.
  echo "==> Warming caches"
  remote "cd '$REMOTE_BASE/api' && php artisan storage:link || true"
  remote "cd '$REMOTE_BASE/api' && php artisan config:cache && php artisan route:cache && php artisan view:cache"
  remote "chown -R www-data:www-data '$REMOTE_BASE/api'"

  remote "systemctl reload $PHP_FPM_SERVICE"
  prune_backups api

  # Built locally and sent over stdin rather than interpolated into a
  # remote-quoted string — a commit subject containing a quote character
  # (e.g. an em dash sentence with "quoted words") would otherwise break the
  # remote shell's parsing and fail the deploy at this last, non-critical
  # step, same as the "Give demo requesters..." commit that caught this.
  deploy_log_line="$(date -u +%FT%TZ)  $(git -C "$REPO_ROOT" rev-parse --short HEAD)  $(git -C "$REPO_ROOT" log -1 --format=%s)"
  printf '%s\n' "$deploy_log_line" | remote "cat >> '$REMOTE_BASE/DEPLOY_LOG'"
}

health_check() {
  echo "==> Health check: $HEALTH_URL"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH_URL" || echo "000")
  if [ "$code" != "200" ]; then
    echo "Health check returned $code, not 200 — consider 'scripts/deploy.sh rollback'." >&2
    exit 1
  fi
  echo "OK ($code)"
}

rollback_one() {
  local name="$1"
  local latest
  latest=$(remote "ls -1d '$REMOTE_BASE/${name}.replaced-'* 2>/dev/null | sort | tail -1")
  if [ -z "$latest" ]; then
    echo "No backup found for $name, nothing to roll back." >&2
    return 1
  fi
  echo "==> Rolling back $name to $latest"
  remote "mv '$REMOTE_BASE/$name' '$REMOTE_BASE/${name}.rolledback-$TS' && mv '$latest' '$REMOTE_BASE/$name' && chown -R www-data:www-data '$REMOTE_BASE/$name'"
  if [ "$name" = "api" ]; then
    remote "systemctl reload $PHP_FPM_SERVICE"
  fi
}

if [ "$MODE" = "rollback" ]; then
  [ "$ROLLBACK_TARGET" != "web" ] && rollback_one api
  [ "$ROLLBACK_TARGET" != "api" ] && rollback_one web
  health_check
  exit 0
fi

require_clean_tree
[ "$DEPLOY_WEB" -eq 1 ] && deploy_web
[ "$DEPLOY_API" -eq 1 ] && deploy_api
health_check
echo "==> Deploy complete: $(git -C "$REPO_ROOT" rev-parse --short HEAD)"
