#!/usr/bin/env bash
# Run ONCE after starting XAMPP MySQL from the XAMPP control panel.
# Wipes and rebuilds clinic_system with fresh migrations + seed.
# Usage: bash scripts/xampp-db-init.sh

set -e

MYSQL="/Applications/XAMPP/xamppfiles/bin/mysql"
PHP="/Applications/XAMPP/xamppfiles/bin/php"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Verify XAMPP MySQL is reachable
if ! $MYSQL -u root --password="" -e "SELECT 1;" >/dev/null 2>&1; then
    echo "ERROR: XAMPP MySQL is not running. Start it from the XAMPP Manager first."
    exit 1
fi

echo "→ Dropping and recreating clinic_system..."
$MYSQL -u root --password="" -e "DROP DATABASE IF EXISTS clinic_system; CREATE DATABASE clinic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "→ Running migrations..."
$PHP "$ROOT/artisan" migrate --force

echo "→ Seeding..."
$PHP "$ROOT/artisan" db:seed --force

echo "→ Linking storage..."
$PHP "$ROOT/artisan" storage:link --force 2>/dev/null || true

echo ""
echo "✓ Done."
echo "  - php artisan serve  →  http://localhost:8000"
echo "  - XAMPP Apache       →  http://localhost (port 80)"
