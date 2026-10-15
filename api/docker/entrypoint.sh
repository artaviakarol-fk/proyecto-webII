#!/bin/sh
set -e

# Dependencias de PHP
composer install --no-interaction --no-progress

# Archivo .env local (no se sube al repositorio)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Clave de la aplicación, solo si todavía no existe
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Migraciones contra PostgreSQL
php artisan migrate --database=pgsql_admin --force

exec "$@"
