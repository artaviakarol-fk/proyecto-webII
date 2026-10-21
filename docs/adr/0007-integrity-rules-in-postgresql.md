# ADR 0007: Reglas de integridad también en PostgreSQL

Estado: aceptada.

## Contexto

El dominio valida cada regla, pero hay escrituras que no pasan por él (migraciones de datos, una consulta escrita a mano) y hay carreras entre peticiones simultáneas que el dominio solo no puede detener.

## Decisión

Las reglas que se pueden expresar en el esquema también viven ahí: `CHECK (stock >= 0)`, cantidades y precios positivos, estados con su lista cerrada de valores, `hora_cierre > hora_apertura`, código de producto único por negocio entre los productos vivos (índice único parcial) y llaves foráneas. Las operaciones que cambian el stock bloquean la fila del producto con `SELECT ... FOR UPDATE`, siempre en el orden de los identificadores, para que dos ventas o entradas simultáneas no se bloqueen entre sí.

## Alternativas descartadas

- **Solo validar en el dominio.** Una carrera entre dos cajas vendería la misma última unidad.
- **Bloqueo optimista con versión.** Obliga a reintentar en cada choque; en la caja de un minisúper los choques son raros y el bloqueo pesimista es más simple.

## Consecuencias

- Si el código fallara, la base rechaza el dato inválido y la petición responde con error en lugar de corromper el stock.
- `LastUnitRaceTest` y `ExpiredUnitsRaceTest` prueban la concurrencia con peticiones reales.
