#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

mkdir -p backend/storage/uploads backend/storage/archive backend/storage/backups
php docker/generate-config.php

echo "Waiting for MariaDB..."
for i in $(seq 1 60); do
  if mariadb-admin ping -h "${DB_HOST:-db}" -u "${DB_USER:-chgpu}" -p"${DB_PASS:-chgpu_dev_password}" --silent >/dev/null 2>&1; then
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "MariaDB did not become ready."
    exit 1
  fi
  sleep 2
done

echo "Applying database schema..."
mariadb -h "${DB_HOST:-db}" -u "${DB_USER:-chgpu}" -p"${DB_PASS:-chgpu_dev_password}" "${DB_NAME:-chgpu_journal}" < backend/schema.sql

echo "Seeding local accounts..."
php docker/seed.php

chown -R www-data:www-data backend/storage 2>/dev/null || true
chmod -R u+rwX,g+rwX backend/storage 2>/dev/null || true

echo "CHGPU local environment is ready."
exec "$@"
