# ADR 0005: Multi-inquilino por un puerto y por seguridad a nivel de fila

Estado: aceptada e implementada. La primera barrera es el adaptador `AuthenticatedNegocioContext` con los filtros de los repositorios; la segunda, las políticas `<tabla>_aislamiento_negocio` descritas en [database.md](../database.md).

## Contexto

Varios negocios comparten la misma instalación y la misma base. Un olvido en una consulta no puede exponer los datos de otro negocio. La rúbrica del Avance 1 pide además comprobar en vivo que no se puede burlar cambiando un dato de la petición.

## Decisión

Hay dos barreras. La primera está en el código: el puerto `NegocioContext` responde a qué negocio pertenece la operación (sale del token, nunca del cuerpo ni de la URL), y cada repositorio filtra por ese `NegocioId`. La segunda está en PostgreSQL: el middleware `negocio` fija `app.negocio_id` en la transacción y toda tabla con `negocio_id` tiene la seguridad a nivel de fila habilitada y forzada. Para que la segunda barrera sirva, la aplicación se conecta con un rol que no es dueño de las tablas (`minisuper_app`).

## Alternativas descartadas

- **Tomar el negocio del cuerpo o de la URL de la petición.** Cualquiera podría operar sobre el negocio de otro.
- **Una base o un esquema por negocio.** Resuelve un problema de escala que el proyecto no tiene y complica las migraciones.
- **Solo un filtro global de Eloquent.** Es una sola barrera y se desactiva con una llamada.

## Consecuencias

- Un recurso de otro negocio responde `404`, igual que uno inexistente.
- Las migraciones corren con un rol y la aplicación con otro.
- `users` lleva `negocio_id` pero no tiene política: el ingreso busca la cuenta por su correo antes de conocer su negocio.
