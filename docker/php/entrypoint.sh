#!/bin/sh
set -e

# Only the "app" container bootstraps the project; worker and scheduler wait for
# it through the compose healthcheck (storage/framework/ready).
if [ "${BOOTSTRAP:-0}" = "1" ]; then
    rm -f storage/framework/ready
    [ -f .env ] || cp .env.example .env
    [ -d vendor ] || composer install --no-interaction --prefer-dist
    grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

    # Wait for MySQL, then migrate. Seed only on the first start.
    until php artisan migrate --force; do echo "waiting for database..."; sleep 3; done
    if [ ! -f storage/framework/seeded ]; then
        php artisan db:seed --force && touch storage/framework/seeded
    fi

    touch storage/framework/ready
fi

exec "$@"
