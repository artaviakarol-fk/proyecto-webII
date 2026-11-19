#!/bin/sh
set -e

# Dependencias de PHP: solo se instalan cuando faltan o cuando cambió composer.lock.
# Revisarlas en cada arranque tardaba varios minutos con la carpeta montada desde Windows.
LOCK_HASH=$(sha1sum composer.lock | cut -d ' ' -f 1)
if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.composer-lock-hash 2>/dev/null)" != "$LOCK_HASH" ]; then
    composer install --no-interaction --no-progress
    echo "$LOCK_HASH" > vendor/.composer-lock-hash
fi

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
