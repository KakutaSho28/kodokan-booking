#!/usr/bin/env sh
set -eu

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -d vendor ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

mkdir -p database
touch database/database.sqlite

php artisan migrate --force
php artisan db:seed --force

exec "$@"
