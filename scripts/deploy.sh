#!/usr/bin/env bash
# ============================================================
# Aviva HealthCare — Production Deploy Script
# Usage: bash scripts/deploy.sh [--skip-build]
# Run from the project root directory.
# ============================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "==> Deploying Aviva HealthCare from: $APP_DIR"

# ── 1. Pull latest code ──────────────────────────────────────
echo "==> Pulling latest code..."
git pull --ff-only

# ── 2. PHP dependencies ──────────────────────────────────────
echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. Frontend build (skip with --skip-build) ───────────────
if [[ "${1:-}" != "--skip-build" ]]; then
    echo "==> Installing Node dependencies and building assets..."
    npm ci
    npm run build
fi

# ── 4. Database migrations ───────────────────────────────────
echo "==> Running database migrations..."
php artisan migrate --force

# ── 5. Clear and rebuild caches ──────────────────────────────
echo "==> Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 6. Storage link ──────────────────────────────────────────
php artisan storage:link --quiet || true

# ── 7. Restart queue workers ─────────────────────────────────
echo "==> Restarting queue workers..."
php artisan queue:restart

# ── 8. Health check ──────────────────────────────────────────
APP_URL=$(php artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -1)
echo "==> Health check: $APP_URL/up"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/up" || echo "000")

if [[ "$HTTP_STATUS" == "200" ]]; then
    echo "==> Deploy complete. App is healthy (HTTP $HTTP_STATUS)."
else
    echo "==> WARNING: Health check returned HTTP $HTTP_STATUS. Check logs."
    exit 1
fi
