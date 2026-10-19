-- Roles y bases de datos de PostgreSQL. Se ejecuta una sola vez por servidor, como superusuario:
--   psql -U postgres -v owner_password='...' -v app_password='...' -f database/roles.sql
--
-- minisuper_owner: dueño del esquema. Solo lo usan las migraciones (conexión pgsql_admin).
-- minisuper_app:   rol de la aplicación. Lee y escribe datos, pero no es dueño de ninguna tabla
--                  ni superusuario, así que Row Level Security siempre se le aplica.

CREATE ROLE minisuper_owner LOGIN PASSWORD :'owner_password';
CREATE ROLE minisuper_app LOGIN PASSWORD :'app_password';

CREATE DATABASE minisuper OWNER minisuper_owner;
CREATE DATABASE minisuper_testing OWNER minisuper_owner;

REVOKE ALL ON DATABASE minisuper, minisuper_testing FROM PUBLIC;
GRANT CONNECT ON DATABASE minisuper, minisuper_testing TO minisuper_app;
