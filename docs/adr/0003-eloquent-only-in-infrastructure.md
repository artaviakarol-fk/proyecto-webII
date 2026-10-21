# ADR 0003: Eloquent solo en la infraestructura

Estado: aceptada.

## Contexto

Eloquent mezcla el dato, la consulta y la persistencia en la misma clase. Si una entidad del dominio extiende `Model`, el dominio depende de Laravel y de la base de datos.

## Decisión

Los modelos Eloquent viven en `Infrastructure/Persistence/Models` y solo los usan los repositorios de `Infrastructure/Persistence/Repositories`. Cada repositorio traduce entre el modelo y la entidad del dominio (Data Mapper), con `create()` para una entidad nueva y `reconstitute()` para una leída de la base. Las consultas que solo leen para mostrar (detalle de una venta, historial de precios) usan el constructor de consultas y devuelven DTOs de `Application/DTOs`.

## Alternativas descartadas

- **Entidades que extienden `Model`.** Más corto, pero el dominio no se puede probar sin base de datos.
- **Repositorio base genérico.** Obliga a cada módulo a implementar operaciones que no usa.

## Consecuencias

- Hay un mapeo explícito por agregado.
- Las pruebas unitarias usan repositorios en memoria (`tests/Support/Inventory/InMemoryProductRepository.php`, etc.).
