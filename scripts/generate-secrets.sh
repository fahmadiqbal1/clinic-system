#!/usr/bin/env bash
# Generates all required production secrets.
# Usage: bash scripts/generate-secrets.sh >> .env.production
set -euo pipefail

gen32() { openssl rand -base64 32; }
gen_pass() { openssl rand -base64 24 | tr -d '/+='; }

echo "APP_KEY=base64:$(gen32)"
echo "CLINIC_CASE_TOKEN_SECRET=$(gen32)"
echo "CLINIC_SIDECAR_JWT_SECRET=$(gen32)"
echo "CLINIC_NOCOBASE_WEBHOOK_SECRET=$(gen32)"
echo "DB_PASSWORD=$(gen_pass | head -c 32)"
echo "MYSQL_ROOT_PASSWORD=$(gen_pass | head -c 32)"
echo "REDIS_PASSWORD=$(gen_pass | head -c 20)"
echo "CLINIC_RO_PASSWORD=$(gen_pass | head -c 24)"
echo "NOCOBASE_DB_PASSWORD=$(gen_pass | head -c 32)"
echo "NOCOBASE_APP_KEY=$(gen32)"
echo "SUPERSET_SECRET_KEY=$(gen32)"
echo "RAGFLOW_MINIO_PASSWORD=$(gen_pass | head -c 20)"
echo "RAGFLOW_MYSQL_ROOT_PASSWORD=$(gen_pass | head -c 20)"
echo "RAGFLOW_MYSQL_PASSWORD=$(gen_pass | head -c 20)"
echo "GRAFANA_ADMIN_PASSWORD=$(gen_pass | head -c 20)"
