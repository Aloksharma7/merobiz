#!/bin/sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
  echo "Waiting for database..."
  until php -r '
    $host = getenv("DB_HOST") ?: "db";
    $port = getenv("DB_PORT") ?: "3306";
    $name = getenv("DB_DATABASE") ?: "merobiz";
    $user = getenv("DB_USERNAME") ?: "merobiz";
    $pass = getenv("DB_PASSWORD") ?: "merobiz";
    try { new PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass); exit(0); }
    catch (Throwable $e) { fwrite(STDERR, "."); exit(1); }
  '; do sleep 2; done
  echo " database ready."
fi

php artisan migrate --force

if [ "${SEED_DEMO:-false}" = "true" ]; then
  php artisan db:seed --force
fi

exec "$@"
