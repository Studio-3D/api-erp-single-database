#!/bin/bash
set -e

CONTAINER_ID=$(docker ps -q | head -n 1)

if [ -z "$CONTAINER_ID" ]; then
  echo "No running Docker container found"
  exit 1
fi

docker exec "$CONTAINER_ID" bash -lc '
cd /var/www

mkdir -p storage

if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
  php artisan passport:keys --force
fi

php artisan optimize:clear
'