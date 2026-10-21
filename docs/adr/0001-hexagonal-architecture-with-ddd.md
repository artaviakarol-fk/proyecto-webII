# ADR 0001: Arquitectura hexagonal con diseño guiado por el dominio

Estado: aceptada.

## Contexto

La API del Minisúper Teresita crece durante todo el curso: catálogo, inventario, ventas, fiado, cobros, avisos por WhatsApp e IA. Las reglas del negocio (stock que nunca es negativo, vencidos que no se venden, licor con cédula) tienen que poder leerse, probarse y cambiarse sin arrastrar el framework ni la base de datos. El docente pidió además la misma estructura del proyecto guía SodaYa-Hexagonal.

## Decisión

El código del producto vive en `api/src/` y se organiza en tres capas: `Domain`, `Application` e `Infrastructure`. Las dependencias apuntan hacia adentro. El dominio declara puertos (interfaces en `Domain/Contracts`, o en `Application/Contracts` cuando el puerto es solo de lectura) y la infraestructura los implementa con adaptadores, enlazados en el proveedor de servicios de cada módulo.

## Alternativas descartadas

- **MVC de Laravel con la lógica en modelos y controladores.** Es más rápido al inicio, pero las reglas quedan repartidas y solo se prueban con base de datos.
- **Capa de servicios sobre Eloquent.** Ordena los controladores, pero el dominio sigue siendo el modelo Eloquent.

## Consecuencias

- El dominio y los casos de uso se prueban sin arrancar Laravel (`tests/Unit`, con dobles en memoria de `tests/Support`).
- Hay más archivos y un mapeo explícito entre entidades y modelos.
- `tests/Architecture/LayerDependencyTest.php` hace cumplir la regla de dependencias en cada cambio.
