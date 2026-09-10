#!/bin/bash
set -e

until php artisan migrate --force; do
  echo "Waiting for database..."
  sleep 2
done

php artisan queue:restart || true

exec "$@"
