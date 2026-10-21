# ADR 0004: Identificadores UUID versión 7

Estado: aceptada.

## Contexto

Un identificador incremental revela cuántos productos o ventas tiene un negocio y permite adivinar los de otro. Un UUID aleatorio (v4) evita eso, pero fragmenta los índices porque no tiene orden.

## Decisión

Toda llave primaria del negocio es `uuid` versión 7 y la genera la aplicación, nunca la base. Hay dos caminos, y los dos producen UUID v7:

- Los agregados que nacen en la API (productos, entradas de mercadería, ventas y unidades vencidas) reciben el identificador de su repositorio, con `nextId()` y `Str::uuid7()`, antes de guardarse. Así el agregado tiene identidad desde que nace y el caso de uso la conoce antes de tocar la base.
- Los modelos de negocios, usuarios y franjas del horario usan el trait `HasUuids` de Eloquent, que en Laravel 13 también llama a `Str::uuid7()`. Esos registros los crea un seeder o se reemplazan enteros (el horario), así que el dominio no necesita su identificador antes de guardar.

Las rutas validan el formato con `whereUuid`, y un identificador inválido responde `404`.

## Alternativas descartadas

- **Enteros autoincrementales.** Exponen el volumen del negocio y facilitan adivinar recursos de otro negocio.
- **UUID v4.** No tiene orden temporal y degrada los índices al insertar.

## Consecuencias

- Los identificadores se ordenan por momento de creación, lo que sirve de desempate en los listados.
- La excepción es `personal_access_tokens.id`, que pertenece a Sanctum y es `bigint`.
