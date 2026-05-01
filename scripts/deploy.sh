#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/clinic-system"
COMPOSE="docker compose"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

log "=== Aviva Clinic -- Deploy starting ==="

cd "$APP_DIR"

log "Pulling latest code..."
git pull origin master

log "Building application containers..."
$COMPOSE build --no-cache app sidecar

log "Running database migrations..."
$COMPOSE run --rm --no-deps app php artisan migrate --force --no-interaction

log "Starting all services..."
$COMPOSE up -d --remove-orphans

log "Waiting for app to be healthy..."
sleep 5

log "Warming caches..."
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache
$COMPOSE exec -T app php artisan event:cache
$COMPOSE exec -T app php artisan optimize

log "Verifying audit chain integrity..."
$COMPOSE exec -T app php artisan audit:verify-chain

log "Restarting queue workers..."
$COMPOSE restart queue

log "=== Deploy complete at $(date) ==="
$COMPOSE ps
