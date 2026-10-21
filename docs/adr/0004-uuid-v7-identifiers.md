# ADR 0004: Identificadores UUID versión 7

Estado: aceptada.

## Contexto

Un identificador incremental revela cuántos productos o ventas tiene un negocio y permite adivinar los de otro. Un UUID aleatorio (v4) evita eso, pero fragmenta los índices porque no tiene orden.

## Decisión

Toda llave primaria del negocio es `uuid` versión 7. La aplicación genera el identificador antes de guardar (`Str::uuid7()` en el método `nextId()` de cada repositorio), así el agregado tiene identidad desde que nace. Las rutas validan el formato con `whereUuid`, y un identificador inválido responde `404`.

## Alternativas descartadas

- **Enteros autoincrementales.** Exponen el volumen del negocio y facilitan adivinar recursos de otro negocio.
- **UUID v4.** No tiene orden temporal y degrada los índices al insertar.

## Consecuencias

- Los identificadores se ordenan por momento de creación, lo que sirve de desempate en los listados.
- La excepción es `personal_access_tokens.id`, que pertenece a Sanctum y es `bigint`.
