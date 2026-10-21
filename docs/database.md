# Base de datos: PostgreSQL 17

El Minisúper Teresita usa PostgreSQL como único motor, en desarrollo, en pruebas y en producción. Este documento fija cómo se conecta la aplicación y cómo se crean y versionan los objetos de la base. El detalle de cada tabla está en [data-model.md](data-model.md).

## Versión

Docker Compose levanta `postgres:17` y el CI corre las pruebas contra el mismo servicio. Las pruebas no usan SQLite: el aislamiento por negocio depende de Row Level Security, que solo existe en PostgreSQL.

## Roles

La aplicación nunca se conecta con el rol dueño de las tablas.

| Rol | Conexión de Laravel | Qué hace | Qué no puede hacer |
| --- | --- | --- | --- |
| `minisuper_owner` | `pgsql_admin` | Es dueño de las bases y ejecuta las migraciones | No atiende peticiones |
| `minisuper_app` | `pgsql` (por defecto) | Lee y escribe filas | Crear, alterar o borrar tablas; no es superusuario ni tiene `BYPASSRLS` |

El script `api/database/roles.sql` crea los dos roles y las dos bases (`minisuper` y `minisuper_testing`). Lo ejecuta una sola vez un superusuario, con las contraseñas como variables de `psql`: nunca se versionan. En Docker lo corre `api/docker/postgres/init-roles.sh` la primera vez que arranca el contenedor de la base.

La primera migración otorga al rol de la aplicación, con `ALTER DEFAULT PRIVILEGES`, los permisos de lectura y escritura sobre toda tabla y secuencia que el dueño cree después. Ninguna tabla nueva necesita un paso manual.

Por qué importa: en PostgreSQL los superusuarios y los roles con `BYPASSRLS` no pasan por las políticas de seguridad a nivel de fila, y el dueño de una tabla tampoco, salvo que la tabla las fuerce. Las tablas de un negocio las fuerzan, pero la aplicación igual se conecta con un rol que no es dueño ni tiene `BYPASSRLS`: el aislamiento no depende de una sola línea de una migración.

## Migraciones

- Se ejecutan siempre con el rol dueño: `php artisan migrate --database=pgsql_admin`. El contenedor de la API lo hace al arrancar.
- Cada migración tiene su `down()` y `MigrationReversibilityTest` las ejecuta hacia atrás y hacia adelante.
- Un cambio que rompe la versión anterior del código se hace en dos despliegues: primero se expande el esquema y, cuando el código viejo ya no corre, se contrae.

## Índices de los listados

Cada consulta frecuente tiene un índice que cubre su filtro y su orden. Primero van las columnas que se comparan con `=` (`negocio_id`, `estado`) y al final la del rango o del orden.

| Consulta | Índice |
| --- | --- |
| Catálogo de un negocio por nombre | `productos_negocio_id_nombre_index` |
| Producto por código dentro del negocio | `productos_negocio_codigo_unique`, único y parcial (`WHERE deleted_at IS NULL`) |
| Ventas de un negocio por estado y fecha | `ventas_negocio_id_estado_created_at_index` |
| Líneas de una venta en su orden | `venta_lineas_venta_id_posicion_index` |
| Entradas de un negocio por fecha | `entradas_mercaderia_negocio_id_fecha_index` |
| Historial de precios de un producto | `historial_precios_negocio_id_producto_id_created_at_index` |
| Movimientos de inventario de un producto | `movimientos_inventario_negocio_id_producto_id_created_at_index` |
| Unidades vencidas activas de un producto | `unidades_vencidas_negocio_id_producto_id_estado_index` |
| Horario de un negocio por día | `horarios_negocio_negocio_id_dia_semana_index` |

## Convenciones del esquema

| Tema | Convención |
| --- | --- |
| Nombres | Español, `snake_case`; tablas en plural (`productos`, `venta_lineas`). Las tablas de Laravel y Sanctum conservan su nombre |
| Llave primaria | `id` de tipo `uuid`, versión 7, generado por la aplicación |
| Negocio | Toda tabla que pertenece a un negocio lleva `negocio_id` con llave foránea a `negocios` |
| Fechas | `timestamptz`; la sesión trabaja en `America/Costa_Rica` |
| Dinero | `numeric(12,2)`; en el dominio, céntimos enteros con `Money` (ADR 0008) |
| Reglas del negocio | Restricción `CHECK` con nombre `<tabla>_<regla>_check` |
| Borrado de productos | Lógico, con `deleted_at`: las ventas y entradas pasadas siguen apuntando al producto |
| Normalización | Tercera forma normal; cada excepción se justifica en `data-model.md` |

## Reglas en la base

| Regla | Restricción |
| --- | --- |
| El stock nunca es negativo | `productos_stock_check`, `movimientos_stock_check` |
| El precio de venta es positivo y el costo no es negativo | `productos_precio_venta_check`, `productos_costo_actual_check`, `historial_precios_precio_check` |
| Las cantidades son positivas | `entrada_lineas_cantidad_check`, `venta_lineas_cantidad_check`, `unidades_vencidas_cantidad_check` |
| Los estados tienen una lista cerrada | `ventas_estado_check`, `unidades_vencidas_estado_check`, `negocios_estado_check`, `users_rol_check`, `movimientos_tipo_check`, `historial_precios_motivo_check` |
| Una franja del horario cierra después de abrir | `horarios_negocio_tramo_check`, `horarios_negocio_dia_check` (1 a 7) |
| El código de un producto vivo no se repite en el negocio | `productos_negocio_codigo_unique` |

## Seguridad a nivel de fila

### Qué protege

Las tablas `productos`, `entradas_mercaderia`, `entrada_lineas`, `historial_precios`, `movimientos_inventario`, `unidades_vencidas`, `ventas`, `venta_lineas` y `horarios_negocio` tienen la seguridad a nivel de fila habilitada y forzada. Cada una tiene la política `<tabla>_aislamiento_negocio`:

```sql
negocio_id = NULLIF(current_setting('app.negocio_id', true), '')::uuid
```

La política vale para leer (`USING`) y para escribir (`WITH CHECK`): una fila de otro negocio no se ve, no se cambia y no se puede insertar. Sin negocio fijado no se ve ninguna fila.

### Cómo se fija el negocio

El middleware `negocio` (`ScopeRequestToNegocio`) toma el `negocio_id` de la cuenta autenticada, abre una transacción y ejecuta `set_config('app.negocio_id', ..., true)`. El tercer argumento hace que el valor dure solo esa transacción, así una conexión reutilizada nunca arrastra el negocio de la petición anterior.

### Excepciones

| Tabla | Por qué no tiene política |
| --- | --- |
| `negocios` | Es la tabla de los inquilinos; la cuenta solo llega a su propio negocio por el `negocio_id` del token |
| `users` | El ingreso busca la cuenta por su correo antes de conocer su negocio |
| `personal_access_tokens` | Pertenece a Sanctum y se busca por el hash del token |

### Cómo agregar una tabla de un negocio

1. Crear la tabla con `negocio_id` y su llave foránea a `negocios`.
2. Llamar `RowLevelSecurity::enable('tabla')` al final de `up()` y `RowLevelSecurity::disable('tabla')` al inicio de `down()`.
3. Proteger sus rutas con el middleware `negocio`.

`RowLevelSecurityTest` falla si una tabla con `negocio_id` (salvo `users`) queda sin su política.

## Reparto de responsabilidades

| Dónde | Qué decide |
| --- | --- |
| Dominio | Todas las reglas del negocio, con mensajes claros para el usuario |
| PostgreSQL | La última red: integridad, aislamiento y concurrencia, aunque el código falle |
| Infraestructura | Traducir entre ambos y bloquear las filas en el orden correcto |
