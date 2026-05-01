#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="/var/backups/clinic"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/clinic-system"
COMPOSE="docker compose -f ${APP_DIR}/docker-compose.yml"

mkdir -p "$BACKUP_DIR"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

log "Starting backup..."

# Load env for DB password
set -a; source "${APP_DIR}/.env.production"; set +a

# MySQL full backup
log "Backing up MySQL..."
$COMPOSE exec -T mysql mysqldump \
    -u root -p"${MYSQL_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    clinic_system > "${BACKUP_DIR}/clinic_${DATE}.sql"

gzip "${BACKUP_DIR}/clinic_${DATE}.sql"
log "MySQL backup: clinic_${DATE}.sql.gz ($(du -sh ${BACKUP_DIR}/clinic_${DATE}.sql.gz | cut -f1))"

# Storage files backup
log "Backing up storage..."
tar -czf "${BACKUP_DIR}/storage_${DATE}.tar.gz" -C "${APP_DIR}" storage/app/

log "Backup complete."

# Rotation -- keep 30 days
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +7 -delete

log "Rotation complete. Current backups:"
ls -lh "$BACKUP_DIR"
