#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force --no-interaction
fi

php artisan config:cache

# Visibility: print which DB the app is actually about to use, so a wrong
# connection/host is obvious in the platform's deploy logs instead of only
# showing up as "tables are empty".
echo "[start.sh] DB_CONNECTION=${DB_CONNECTION:-unset} DB_HOST=${DB_HOST:-unset} DB_DATABASE=${DB_DATABASE:-unset}"

php artisan migrate --force --no-interaction

# Seed only once. Re-running "db:seed --force" on every boot either
# duplicates rows or crashes on unique-constraint violations (which, under
# `set -e`, would kill this script before `artisan serve` ever starts). Guard
# on an empty `users` table so a fresh/empty database gets seeded and an
# already-seeded one is left alone.
USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n 1)"
if [ "$USER_COUNT" = "0" ]; then
  echo "[start.sh] users table is empty, running db:seed..."
  php artisan db:seed --force
else
  echo "[start.sh] users table already has data (${USER_COUNT} rows), skipping db:seed."
fi

php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"