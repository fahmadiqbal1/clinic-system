#!/bin/bash
set -e

echo "==> Superset init starting"

superset db upgrade

superset fab create-admin \
  --username admin \
  --firstname Admin \
  --lastname Clinic \
  --email admin@clinic.local \
  --password admin 2>/dev/null || echo "Admin user already exists, skipping"

superset init

# Register the clinic_system MariaDB datasource via YAML import (idempotent)
if [ -f /app/clinic_datasource.yaml ]; then
  superset import_datasources -p /app/clinic_datasource.yaml 2>/dev/null \
    || echo "Datasource import skipped (may already exist or command unavailable)"
fi

echo "==> Superset init complete — starting server on port 8088"
exec superset run -p 8088 --with-threads --host 0.0.0.0
