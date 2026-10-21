# ADR 0002: Módulos agrupados por contexto delimitado

Estado: aceptada.

## Contexto

Un minisúper tiene áreas con vocabulario propio: el inventario habla de entradas, costos y vencidos; la caja, de ventas, cobros y anulaciones. Si todo vive en una sola carpeta por capa, cada cambio toca archivos de áreas que no tienen nada que ver.

## Decisión

El código se organiza como `src/<Contexto>/<Módulo>/<Capa>`, igual que el proyecto guía:

| Contexto | Módulos | Equivalente en SodaYa |
| --- | --- | --- |
| `Identity` | `Users` | `Identity/Users` |
| `Negocios` | `Profile`, `OpeningHours` | `Sodas/Profile`, `Sodas/OpeningHours` |
| `Inventory` | `Products`, `Receipts`, `Expirations` | `Catalog/Dishes`, `Catalog/Categories` |
| `Sales` | `Sales` | `Ordering/Orders` |
| `Shared` | núcleo compartido | `Shared` |

Lo que comparten los módulos de un contexto vive en `<Contexto>/Shared` (por ejemplo `ProductId`). Lo que comparten todos los contextos vive en `src/Shared` (`Money`, `NegocioId`, `Page`, los puertos de transacción, imágenes y negocio actual).

## Alternativas descartadas

- **Una carpeta por capa para toda la aplicación.** Mezcla áreas y vuelve difícil saber qué módulo depende de cuál.
- **Un paquete de Composer por contexto.** Agrega versionado y configuración que un equipo de dos personas no necesita.

## Consecuencias

- El dominio de un módulo no importa el dominio de otro módulo; la prueba de arquitectura lo revisa. Cuando `Sales` necesita datos del inventario los pide por un puerto propio (`ProductCatalog`).
- Cada módulo tiene su proveedor de servicios y su archivo de rutas.
