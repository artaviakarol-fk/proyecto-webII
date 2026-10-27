# Patrones de diseño

Ningún patrón se usa por tenerlo. Cada uno resuelve un problema concreto del proyecto; si el problema no existe, el patrón tampoco.

## Patrones adoptados

| Patrón | Dónde vive | Problema que resuelve |
| --- | --- | --- |
| Puertos y adaptadores | `Domain/Contracts`, `Application/Contracts` e `Infrastructure` | El negocio no depende del framework ni de la base de datos |
| Repository | Contrato en `Domain/Contracts`, adaptador en `Infrastructure/Persistence/Repositories` | Guardar y recuperar agregados sin exponer la persistencia |
| Data Mapper | Dentro de los repositorios: `toDomain()` en productos y usuarios, `reconstitute()` directo en negocios y ventas | Separar la entidad del dominio del modelo Eloquent o de la fila |
| Aggregate | `Domain/Entities` (`Product`, `GoodsReceipt`, `Sale`, `ExpiredUnits`) | Un solo punto de entrada para cambiar un conjunto de datos que se valida junto |
| Value Object | `Domain/ValueObjects` y `src/Shared/Domain/ValueObjects` (`Money`, `Stock`, `ProductCode`, `BuyerIdNumber`, `WeeklySchedule`) | La regla de un dato vive en un solo lugar; es imposible crear uno inválido |
| Factory Method | `create()` y `reconstitute()` en cada agregado | Distinguir el nacimiento de un agregado de su lectura desde la base |
| DTO / Command | `Application/DTOs` (`ProductData`, `SaleFilter`, `SaleDetails`) | Cruzar capas con datos simples, sin `Request` ni modelos |
| Inyección de dependencias | Proveedor de servicios de cada módulo (`$bindings`) | Elegir el adaptador de cada puerto en un solo lugar |
| Modelo de lectura (CQRS simple) | `Infrastructure/Persistence/Queries` (`DatabaseSaleReader`, `DatabaseProductCatalog`) y `DatabasePriceHistoryReader` | Consultas a la medida de la respuesta sin cargar agregados |
| Página de un listado | `Page`, `PageRequest`, `ListRequest`, `PageMeta`, `EloquentPages` en `src/Shared` | Todos los listados paginan igual y responden el mismo `meta` |
| Cadena de responsabilidad | Middleware HTTP (`AssignRequestId`, `ScopeRequestToNegocio`, `EncodeJsonAsUnicode`) | Tratar cada petición antes y después del controlador |
| Unidad de trabajo | `TransactionRunner` y `NegocioTransaction` | Que una entrada o una venta guarde todo o nada, dentro de la transacción del negocio |
| Transacción y bloqueo pesimista | `DatabaseSaleRepository`, `EloquentGoodsReceiptRepository`, `DatabaseExpiredUnitsRepository` | Que dos cajas no vendan la misma última unidad: `FOR UPDATE` en el orden de los identificadores |
| Estado como enum con transiciones | `SaleStatus` en `Sales/Sales`, `ExpiredUnitsStatus` en `Inventory/Expirations` | Impedir un cambio de estado inválido (una venta anulada es definitiva) |
| Strategy y capa anticorrupción | `PaymentGateway` y `Billing/Payments/Infrastructure/Gateways` | Cambiar de pasarela de pago por configuración y traducir la API de cada proveedor al dominio (ADR 0010) |
| Documento de problema | `ProblemDetailsRenderer` y `ProblemType` | Todos los errores salen en RFC 9457 con el mismo formato |

## Patrones previstos

| Patrón | Módulo | Para qué |
| --- | --- | --- |
| Policy | Cuentas y roles (Avance 2) | Decidir quién puede hacer qué además de la ability del token |
| Clave de idempotencia | Webhook de cobro | Que un reintento del proveedor no confirme dos veces el mismo cobro |
| Capa anticorrupción | WhatsApp | Traducir la forma de cada API externa a objetos del dominio |
| Observer (eventos de dominio) | Ventas y avisos | Avisar a otros contextos sin acoplarlos |

## Descartados

| Patrón | Motivo |
| --- | --- |
| Bus de comandos y manejadores | El controlador llama al caso de uso directamente |
| Repositorio base genérico | Obliga a implementar operaciones que el módulo no usa |
| Una clase por estado (State) | Un enum con sus transiciones válidas cubre el caso |
| Specification y Abstract Factory | No hay un problema en el proyecto que los pida |
| Event Sourcing | Su complejidad supera lo que el negocio necesita; `movimientos_inventario` ya deja la traza del stock |
| Capa `Presentation` separada | En una API el controlador es un adaptador más de la infraestructura |
