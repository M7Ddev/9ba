#!/bin/sh
# Container start-up for صَبّة.
#
# Everything here is idempotent, because a container restarts for all sorts of
# reasons and must never damage state it finds.

set -e

# SQLite needs the file to exist before Laravel will open it. Harmless when the
# app is pointed at Postgres or MySQL instead.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  if [ ! -f "$DB_FILE" ]; then
    echo "Creating SQLite database at $DB_FILE"
    touch "$DB_FILE"
  fi
fi

# --force because migrate refuses to run unattended in production otherwise.
echo "Running migrations..."
php artisan migrate --force

# Cached config and routes are a large start-up win, and are safe to rebuild on
# every boot. Cleared first so a stale cache from a previous image can never
# survive a deploy.
php artisan config:clear
php artisan config:cache
php artisan route:cache

# A missing APP_KEY leaves encryption and signed URLs broken in ways that
# surface much later and confusingly. Fail loudly instead.
if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
  exit 1
fi

# Railway, Render, Fly and Cloud Run all assign a port at runtime through $PORT
# and route only to that port. A hardcoded listen address works locally and then
# fails on the host with no obvious cause, so honour $PORT and fall back to 8080.
PORT="${PORT:-8080}"
export SERVER_NAME=":${PORT}"

# An explicit command (docker run ... <cmd>) wins, so the image stays debuggable.
if [ "$#" -gt 0 ]; then
  echo "Starting: $*"
  exec "$@"
fi

echo "Starting server on :${PORT}"
exec frankenphp php-server --listen ":${PORT}" --root /app/public
