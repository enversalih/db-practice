#!/bin/sh
set -eu

echo "PostgreSQL bağlantısı bekleniyor..."
until php -r '$s=@fsockopen(getenv("DB_HOST"), (int)getenv("DB_PORT"), $e, $m, 1); if ($s) { fclose($s); exit(0); } exit(1);'; do
  sleep 2
done

php artisan migrate --force
php artisan hbys:seed "${DATASET_PROFILE:-small}"
php artisan optimize:clear

exec "$@"
