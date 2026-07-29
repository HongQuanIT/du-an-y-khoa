#!/bin/sh
set -e

# First-run bootstrap for local dev (source is bind-mounted).
if [ ! -d vendor ]; then
    echo "[entrypoint] Installing composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    echo "[entrypoint] Creating .env from .env.example..."
    cp .env.example .env
fi

# Generate an app key if it is empty.
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force || true
fi

# Ensure writable runtime dirs.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R ug+rw storage bootstrap/cache || true

exec "$@"
