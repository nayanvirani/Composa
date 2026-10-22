#!/bin/sh
# Entry point for every Railway service built from the Composa image (see Dockerfile).
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize || true

case "${ROLE:-web}" in
  worker)
    exec php artisan horizon
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  web)
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
      php artisan migrate --force
      php artisan db:seed --force
    fi
    exec frankenphp run --config /etc/caddy/Caddyfile
    ;;
  *)
    echo "Unknown ROLE: $ROLE (expected web, worker or scheduler)" >&2
    exit 1
    ;;
esac
