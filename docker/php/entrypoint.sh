#!/usr/bin/env bash
set -euo pipefail

# The role decides how much bootstrapping happens:
#
#   app        wait for the stores, migrate, sync indexes, warm caches
#   worker     wait for the stores, nothing else
#   scheduler  same as worker
#   cli        nothing at all
#
# `cli` is what one-off commands use. A unit test run or a `pint` invocation needs no
# database, and blocking it for sixty seconds waiting for one it will never open is
# the difference between a usable and an unusable dev loop.
ROLE="${CONTAINER_ROLE:-cli}"

if [ "$ROLE" = "cli" ]; then
    exec "$@"
fi

wait_for() {
    local name="$1" host="$2" port="$3" attempts=0
    until (echo > "/dev/tcp/${host}/${port}") >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 60 ]; then
            echo "[entrypoint] giving up waiting for ${name} at ${host}:${port}" >&2
            exit 1
        fi
        sleep 1
    done
}

# Compose gates start-up on healthchecks already; these waits are what make the
# image safe outside compose, and on a restart where a store is still coming back.
if [ -n "${DB_HOST:-}" ]; then
    wait_for postgres "${DB_HOST}" "${DB_PORT:-5432}"
fi

if [ -n "${REDIS_HOST:-}" ]; then
    wait_for redis "${REDIS_HOST}" "${REDIS_PORT:-6379}"
fi

if [ -n "${MONGO_HOST:-}" ]; then
    wait_for mongodb "${MONGO_HOST}" "${MONGO_PORT:-27017}"
fi

if [ "$ROLE" = "app" ]; then
    if [ -z "${APP_KEY:-}" ] && [ -f .env ] && ! grep -q '^APP_KEY=base64' .env; then
        php artisan key:generate --force --ansi
    fi

    php artisan migrate --force --ansi
    php artisan shortwave:mongo-sync --ansi

    if [ "${APP_ENV:-local}" = "production" ]; then
        php artisan config:cache --ansi
        php artisan route:cache --ansi
        php artisan event:cache --ansi
    else
        php artisan optimize:clear --ansi
    fi
fi

exec "$@"
