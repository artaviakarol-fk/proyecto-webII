# Lenguaje ubicuo y convenciones de nombres

La misma palabra significa lo mismo en la conversación con el dueño del minisúper, en el contrato de la API, en el código y en la base de datos. Este documento relaciona cada término del negocio con su nombre en cada lugar.

## Convención de idioma

| Dónde | Idioma | Ejemplo |
| --- | --- | --- |
| Clases, métodos, variables y comentarios | Inglés | `Product`, `GoodsReceipt`, `isSoldOut()` |
| Tablas y columnas | Español, `snake_case` | `productos.precio_venta` |
| Rutas de la API | Español, en plural | `/api/v1/productos/{producto}/disponibilidad` |
| Atributos JSON | Español, `snake_case` | `stock_vendible` |
| Mensajes al usuario | Español, desde `lang/es` | "No alcanza el stock de Arroz 1 kg." |

Las rutas y los atributos JSON son parte de lo que ve quien consume la API, por eso usan el vocabulario del negocio. La traducción entre ambos idiomas ocurre en un solo lugar: los Form Requests de entrada y los API Resources de salida.

`Negocio` se conserva en el código (`NegocioId`, `NegocioContext`) porque es el término con que el curso nombra al inquilino y no tiene un equivalente exacto en inglés que el equipo use al hablar.

A diferencia del proyecto guía, las tablas usan el español del negocio: el dueño revisa los datos con el equipo y así los nombres coinciden con la API.

## Glosario

| Negocio | Código | Base de datos | API |
| --- | --- | --- | --- |
| Negocio (minisúper) | `Negocio` | `negocios` | `negocio` |
| Logo | `logoPath` | `negocios.logo_path` | `logo` |
| Horario | `WeeklySchedule` | `horarios_negocio` | `horario` |
| Franja | `TimeSlot` | fila de `horarios_negocio` | elemento de `dias` |
| Día de la semana | `Weekday` | `dia_semana` | `lunes` … `domingo` |
| Hora de apertura | `TimeSlot::opens` (`TimeOfDay`) | `hora_apertura` | `abre` |
| Hora de cierre | `TimeSlot::closes` (`TimeOfDay`) | `hora_cierre` | `cierra` |
| Abierto ahora | `isOpenAt()` | — | `abierto_ahora` |
| Producto | `Product` | `productos` | `productos` |
| Código | `ProductCode` | `codigo` | `codigo` |
| Nombre | `ProductName` | `nombre` | `nombre` |
| Categoría | `Category` | `categoria` | `categoria` |
| Precio de venta | `salePrice` (`Money`) | `precio_venta` | `precio_venta` |
| Costo | `currentCost` (`Money`) | `costo_actual` | `costo_actual` |
| Stock | `Stock` | `stock` | `stock` |
| Perecedero | `perishable` | `es_perecedero` | `es_perecedero` |
| Licor | `liquor` | `es_licor` | `es_licor` |
| Foto | `photoPath` | `foto_path` | `foto` |
| Agotado | `isSoldOut()` | — | `agotado` |
| Disponibilidad | `Availability` | — | `disponibilidad` |
| Stock vendible | `Availability::sellable` | — | `stock_vendible` |
| Entrada de mercadería | `GoodsReceipt` | `entradas_mercaderia` | `entradas` |
| Línea de la entrada | `ReceiptLine` | `entrada_lineas` | `lineas` |
| Proveedor | `Supplier` | `proveedor` | `proveedor` |
| Costo unitario | `unitCost` | `costo_unitario` | `costo_unitario` |
| Costo anterior | — | `costo_anterior` | `costo_anterior` |
| Historial de precios | `PriceHistoryEntry` | `historial_precios` | `historial-precios` |
| Motivo del cambio | `PriceChangeReason` | `motivo` | `motivo` |
| Movimiento de inventario | — | `movimientos_inventario` | — |
| Unidades vencidas | `ExpiredUnits` | `unidades_vencidas` | `vencidos`, `unidades_vencidas` |
| Venta | `Sale` | `ventas` | `ventas` |
| Línea de la venta | `SaleLine` | `venta_lineas` | `lineas` |
| Estado de la venta | `SaleStatus` | `estado` | `estado` |
| Cobrar | `PaySale` | `cobrada_at` | `cobrar`, `cobrada_en` |
| Anular | `CancelSale` | `anulada_at` | `anular`, `anulada_en` |
| Cédula del comprador | `BuyerIdNumber` | `cedula_comprador` | `cedula_comprador` |
| Cajero que vendió | `cashierId` | `cajero_id` | `cajero` |
| Usuario | `User` | `users` | — |
| Correo | `Email` | `correo` | `correo` |
| Contraseña | — | `password`, solo el hash | `contrasena` |
| Token de acceso | — | `personal_access_tokens` | `tokens`, `token` |
| Dispositivo | `deviceName` | `personal_access_tokens.name` | `dispositivo` |
| Rol | `Role` | `rol` | — |
| Ability | `Role::abilities()` | `personal_access_tokens.abilities` | `abilities` |
| Página de un listado | `Page`, `PageRequest` | — | `pagina`, `por_pagina`, `meta` |

Los términos de los contextos que aún no existen (fiado, abono, cobro con tarjeta, saldo prepagado, lista de espera) quedan reservados en los issues de los milestones M2 a M5 y se agregan aquí cuando se construyan.

## Roles

| Rol | Código | Abilities | Qué hace |
| --- | --- | --- | --- |
| Administrador | `Role::Administrador` | `administrar`, `caja`, `inventario` | Todo: catálogo, precios, horario, logo, entradas, vencidos y caja |
| Cajero | `Role::Cajero` | `caja` | Vende, cobra y anula; consulta el catálogo y la disponibilidad |
| Bodeguero | `Role::Bodeguero` | `inventario` | Registra entradas y marca vencidos; consulta el catálogo |

## Ventas

- Una venta nace **abierta**: sus unidades ya salieron del stock.
- Una venta abierta se puede **modificar**; el stock se ajusta por la diferencia.
- **Cobrar** la cierra; una venta cobrada ya no se modifica.
- **Anular** devuelve sus unidades al stock; una venta anulada es definitiva.
- El precio de cada línea es el del catálogo en el momento de vender; el cliente de la API nunca lo envía.

## Vencidos

- Las **unidades vencidas** activas siguen en el estante pero no se venden: el **stock vendible** es el stock menos esas unidades.
- La venta de un producto con unidades vencidas no se bloquea: la respuesta trae una **advertencia** para el cajero, porque puede que solo una unidad esté vencida (respuesta del dueño en la entrevista).

## Dinero y fechas

- Los montos son colones con dos decimales. En el dominio son céntimos enteros (`Money`) y en la base `numeric(12,2)`.
- Las fechas se muestran en ISO 8601 con el desfase de Costa Rica (`-06:00`). Un filtro por `fecha` se entiende como el día en Costa Rica.

## Cobros

| Término | Significado |
| --- | --- |
| Pago | Intento de cobrar una venta con un proveedor. Está `pendiente`, `pagado`, `fallido` o `reembolsado` |
| Reembolso | Devolución del monto total de un pago pagado, con el mismo proveedor que lo cobró. Solo la administración lo hace |
| Medios soportados | Métodos de pago que una pasarela declara (`supportedMethods()`); ONVO: tarjeta y SINPE Móvil, PayPal: solo tarjeta |
| Pasarela | Servicio externo que mueve el dinero (ONVO Pay, PayPal). El sistema la usa a través del puerto `PaymentGateway` |
| Método de pago del proveedor | Identificador que el cliente crea directamente con el proveedor para la tarjeta; la API nunca ve el número |
| SINPE Móvil | Transferencia que el comprador hace al número del negocio; el pago queda pendiente hasta que el proveedor la confirma |
