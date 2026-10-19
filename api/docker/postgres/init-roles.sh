#!/bin/sh
# Lo ejecuta la imagen de PostgreSQL la primera vez que crea el volumen de datos:
# crea los roles minisuper_owner y minisuper_app y las bases minisuper y minisuper_testing.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
    -v owner_password="$DB_ADMIN_PASSWORD" \
    -v app_password="$DB_PASSWORD" \
    -f /docker-entrypoint-initdb.d/roles.sql.in
