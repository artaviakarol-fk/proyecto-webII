# Modelo de datos

El esquema está en tercera forma normal. Este documento describe las tablas que ya existen; cada módulo completa las suyas en el pull request que agrega su migración.

Las convenciones de nombres, llaves, fechas y dinero están en [database.md](database.md). Las tablas `productos`, `entradas_mercaderia`, `entrada_lineas`, `historial_precios`, `movimientos_inventario`, `unidades_vencidas`, `ventas`, `venta_lineas` y `horarios_negocio` tienen seguridad a nivel de fila forzada: cada transacción solo alcanza las filas de su negocio.

## Diagrama

```mermaid
erDiagram
    negocios ||--o{ users : "emplea"
    users ||--o{ personal_access_tokens : "ingresa con"
    negocios ||--o{ horarios_negocio : "atiende en"
    negocios ||--o{ productos : "vende"
    negocios ||--o{ entradas_mercaderia : "recibe"
    users ||--o{ entradas_mercaderia : "registra"
    entradas_mercaderia ||--|{ entrada_lineas : "contiene"
    productos ||--o{ entrada_lineas : "llega en"
    productos ||--o{ historial_precios : "cambia de precio en"
    users ||--o{ historial_precios : "cambia"
    productos ||--o{ movimientos_inventario : "mueve stock en"
    users ||--o{ movimientos_inventario : "provoca"
    productos ||--o{ unidades_vencidas : "tiene vencidas"
    users ||--o{ unidades_vencidas : "marca"
    negocios ||--o{ ventas : "registra"
    users ||--o{ ventas : "cobra"
    ventas ||--|{ venta_lineas : "contiene"
    productos ||--o{ venta_lineas : "se vende en"
    negocios ||--o{ clientes : "fía a"
    clientes ||--o{ movimientos_fiado : "debe por"
    ventas ||--o| movimientos_fiado : "se carga en"
    users ||--o{ movimientos_fiado : "registra"
    clientes ||--o{ ampliaciones_limite : "recibe"
    users ||--o{ ampliaciones_limite : "otorga o revierte"
    clientes ||--o{ abonos : "paga con"
    abonos ||--|| movimientos_fiado : "se registra en"
    users ||--o{ abonos : "recibe"
```

## Tablas del negocio

### negocios

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `nombre` | `varchar(120)` | |
| `logo_path` | `varchar(255)` | Opcional; ruta en el disco privado, dentro de `negocios/{id}/logo/` |
| `estado` | `varchar(20)` | `activo` o `inactivo` |
| `created_at`, `updated_at` | `timestamptz` | |

### horarios_negocio

Una fila por franja de atención. Un día sin filas es un día cerrado.

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `dia_semana` | `smallint` | 1 (lunes) a 7 (domingo), ISO 8601 |
| `hora_apertura`, `hora_cierre` | `time` | El cierre es posterior a la apertura |
| `created_at`, `updated_at` | `timestamptz` | |

## Tablas del inventario

### productos

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `codigo` | `varchar(30)` | Único por negocio entre los productos vivos |
| `nombre` | `varchar(120)` | Con índice `(negocio_id, nombre)` |
| `categoria` | `varchar(60)` | Opcional |
| `precio_venta` | `numeric(12,2)` | Mayor que cero |
| `costo_actual` | `numeric(12,2)` | Cero o más; lo actualiza cada entrada |
| `stock` | `integer` | Cero o más |
| `es_perecedero`, `es_licor` | `boolean` | El licor exige la cédula del comprador al vender |
| `foto_path` | `varchar(255)` | Opcional; ruta en el disco privado |
| `created_at`, `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | Borrado lógico |

### entradas_mercaderia y entrada_lineas

| Columna de `entradas_mercaderia` | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `proveedor` | `varchar(120)` | |
| `fecha` | `date` | No futura; índice `(negocio_id, fecha)` |
| `registrada_por` | `uuid` | Llave foránea a `users` |
| `created_at`, `updated_at` | `timestamptz` | |

| Columna de `entrada_lineas` | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `entrada_id` | `uuid` | Llave foránea a `entradas_mercaderia`, en cascada |
| `producto_id` | `uuid` | Llave foránea a `productos` |
| `cantidad` | `integer` | Mayor que cero |
| `costo_unitario` | `numeric(12,2)` | Cero o más |
| `costo_anterior` | `numeric(12,2)` | Opcional; el costo que tenía el producto antes de esta entrada |
| `created_at`, `updated_at` | `timestamptz` | |

### historial_precios

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id`, `producto_id`, `usuario_id` | `uuid` | Llaves foráneas |
| `costo`, `precio_venta` | `numeric(12,2)` | Valores después del cambio |
| `motivo` | `varchar(20)` | `creacion`, `edicion`, `entrada` o `cambio_precio` |
| `referencia_id` | `uuid` | Opcional; la entrada que provocó el cambio |
| `created_at`, `updated_at` | `timestamptz` | |

### movimientos_inventario

Una fila por cada cambio del stock, para la trazabilidad.

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id`, `producto_id`, `usuario_id` | `uuid` | Llaves foráneas |
| `tipo` | `varchar(20)` | `entrada`, `venta`, `anulacion`, `vencimiento` o `ajuste` |
| `cantidad` | `integer` | Unidades del movimiento, siempre positivas; el `tipo` dice si suman o restan |
| `stock_resultante` | `integer` | Cero o más |
| `referencia_id` | `uuid` | Opcional; la entrada o la venta que lo provocó |
| `created_at`, `updated_at` | `timestamptz` | |

### unidades_vencidas

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id`, `producto_id`, `usuario_id` | `uuid` | Llaves foráneas |
| `cantidad` | `integer` | Mayor que cero |
| `motivo` | `varchar(200)` | |
| `estado` | `varchar(20)` | `activa` (sigue en el estante y no se vende) o `retirada` |
| `created_at`, `updated_at` | `timestamptz` | |

## Tablas de ventas

### ventas

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `cajero_id` | `uuid` | Llave foránea a `users` |
| `metodo_pago` | `varchar(20)` | `efectivo`, `tarjeta`, `sinpe_movil` o `fiado` |
| `estado` | `varchar(20)` | `abierta`, `cobrada` o `anulada`; índice `(negocio_id, estado, created_at)` |
| `total` | `numeric(12,2)` | Cero o más |
| `cedula_comprador` | `varchar(20)` | Opcional; obligatoria si alguna línea es de licor |
| `cobrada_at`, `anulada_at` | `timestamptz` | Opcionales |
| `created_at`, `updated_at` | `timestamptz` | |

### venta_lineas

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `venta_id` | `uuid` | Llave foránea a `ventas`, en cascada |
| `producto_id` | `uuid` | Llave foránea a `productos` |
| `posicion` | `smallint` | Orden de la línea en la venta |
| `cantidad` | `integer` | Mayor que cero |
| `precio_unitario` | `numeric(12,2)` | Mayor que cero; el precio del catálogo al vender |
| `subtotal` | `numeric(12,2)` | |
| `created_at`, `updated_at` | `timestamptz` | |

## Tablas de cobros

### pagos

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `venta_id` | `uuid` | Llave foránea compuesta `(venta_id, negocio_id)` a `ventas` |
| `abono_id` | `uuid` | Opcional; llave foránea a `abonos`, para el pago de un abono con pasarela (el pago es de una venta o de un abono, nunca de ambos) |
| `proveedor` | `varchar(20)` | `onvo` o `paypal` |
| `metodo_pago` | `varchar(20)` | `tarjeta` o `sinpe_movil` |
| `moneda` | `char(3)` | `CRC` |
| `monto` | `numeric(12,2)` | Mayor que cero |
| `estado` | `varchar(20)` | `pendiente`, `pagado`, `fallido` o `reembolsado` |
| `referencia_externa` | `varchar(255)` | Identificador del cobro en el proveedor; único junto con `proveedor` |
| `referencia_reembolso` | `varchar(255)` | Identificador del reembolso en el proveedor; existe si y solo si el estado es `reembolsado` |
| `codigo_rechazo` | `varchar(100)` | Solo si el estado es `fallido` |
| `payload` | `jsonb` | Respuesta reducida del proveedor, sin datos de tarjeta |
| `resuelto_at` | `timestamptz` | Solo si el pago ya no está pendiente |
| `created_at`, `updated_at` | `timestamptz` | |

Un índice único parcial `pagos_venta_viva_unique` impide dos pagos pendientes o pagados para la misma venta; un pago fallido o reembolsado deja cobrarla de nuevo. Tiene aislamiento por negocio con Row Level Security.

## Tablas de fiado

### clientes

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios`; índice `(negocio_id, nombre)` |
| `nombre` | `varchar(120)` | |
| `telefono` | `varchar(20)` | Opcional |
| `limite_fiado` | `numeric(12,2)` | Cero o más; por defecto el límite base (`LIMITE_FIADO_BASE`, ₡90 000), que también es el máximo que puede definir la administración |
| `saldo_actual` | `numeric(12,2)` | Cero o más; lo que el cliente debe. Solo lo cambian los movimientos de fiado |
| `activo` | `boolean` | Un cliente no se borra: se desactiva y conserva su historial |
| `created_at`, `updated_at` | `timestamptz` | |

El rol de la aplicación no puede escribir `saldo_actual` ni borrar clientes: tiene permiso de `INSERT` y `UPDATE` solo sobre las demás columnas, y ninguno de `DELETE`. Tiene aislamiento por negocio con Row Level Security.

### movimientos_fiado

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios` |
| `cliente_id` | `uuid` | Llave foránea a `clientes`; índice `(negocio_id, cliente_id, created_at)` |
| `tipo` | `varchar(10)` | `cargo` (una venta a fiado, con `venta_id`), `anulacion` (la anulación de esa venta, con el mismo `venta_id`) o `abono` (con `abono_id`) |
| `monto` | `numeric(12,2)` | Mayor que cero |
| `venta_id` | `uuid` | Llave foránea a `ventas`; solo en un cargo o una anulación. Una venta se carga una sola vez y se anula una sola vez |
| `abono_id` | `uuid` | Llave foránea a `abonos`; solo en un abono, y un abono se registra una sola vez |
| `usuario_id` | `uuid` | Llave foránea a `users`: quién registró el movimiento |
| `created_at` | `timestamptz` | |

Es un libro de movimientos: las filas solo se agregan. El trigger `trg_movimientos_fiado_aplicar` mueve el `saldo_actual` del cliente en la misma sentencia que inserta el movimiento (suma un cargo, resta una anulación o un abono), así que el saldo siempre es la suma del libro; la función corre con los permisos del dueño del esquema porque la aplicación no puede escribir el saldo. El trigger `trg_movimientos_fiado_inmutables` rechaza cambiar o borrar un movimiento. El `CHECK (saldo_actual >= 0)` de `clientes` impide un abono mayor que la deuda. Tiene aislamiento por negocio con Row Level Security.

### ampliaciones_limite

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios`; índices `(negocio_id, cliente_id, vigente_hasta)` y `(negocio_id, created_at)` |
| `cliente_id` | `uuid` | Llave foránea a `clientes` |
| `monto` | `numeric(12,2)` | Mayor que cero; se suma al límite mientras está vigente |
| `motivo` | `varchar(255)` | Obligatorio |
| `usuario_id` | `uuid` | Llave foránea a `users`: quién la otorgó |
| `vigente_hasta` | `timestamptz` | La medianoche siguiente en hora de Costa Rica; posterior a `created_at` |
| `revertida_at` | `timestamptz` | Opcional; cuándo la revirtió la administración |
| `revertida_por` | `uuid` | Llave foránea a `users`; existe si y solo si existe `revertida_at` |
| `created_at`, `updated_at` | `timestamptz` | `created_at` es cuándo se otorgó |

Es la auditoría de las ampliaciones de emergencia (US-304): nunca se borran (el rol de la aplicación no tiene `DELETE`) y nunca cambian el `limite_fiado` del cliente. El límite efectivo es el límite base más las ampliaciones no revertidas que no han vencido, y nunca pasa de `LIMITE_FIADO_MAXIMO` (₡120 000). Tiene aislamiento por negocio con Row Level Security.

### abonos

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria; los últimos 8 caracteres son el número del recibo |
| `negocio_id` | `uuid` | Llave foránea a `negocios`; índice `(negocio_id, cliente_id, created_at)` |
| `cliente_id` | `uuid` | Llave foránea a `clientes` |
| `monto` | `numeric(12,2)` | Mayor que cero |
| `metodo_pago` | `varchar(20)` | Por ahora solo `efectivo`; la pasarela llega en otro PR |
| `saldo_anterior` | `numeric(12,2)` | Lo que debía el cliente antes del abono |
| `saldo_restante` | `numeric(12,2)` | Cero o más, e igual a `saldo_anterior - monto` |
| `usuario_id` | `uuid` | Llave foránea a `users`: quién lo recibió |
| `created_at` | `timestamptz` | |

Cada abono guarda los saldos de antes y de después, así su recibo siempre muestra lo mismo. El abono y su movimiento `abono` en `movimientos_fiado` se registran en la misma transacción, y el trigger del libro baja el saldo del cliente. La aplicación no puede cambiar ni borrar un abono. Tiene aislamiento por negocio con Row Level Security.

## Tablas de identidad

### users

| Columna | Tipo | Detalle |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria |
| `negocio_id` | `uuid` | Llave foránea a `negocios`; sin política de fila (ver database.md) |
| `nombre` | `varchar(120)` | |
| `correo` | `varchar(255)` | Único |
| `password` | `varchar(255)` | Solo el hash |
| `rol` | `varchar(20)` | `administrador`, `cajero` o `bodeguero` |
| `activo` | `boolean` | Una cuenta inactiva no inicia sesión |
| `created_at`, `updated_at` | `timestamptz` | |

### personal_access_tokens

La tabla de Sanctum: `id` (`bigint`), `tokenable_type`, `tokenable_id` (`uuid`), `name` (el dispositivo), `token` (hash SHA-256, único), `abilities`, `last_used_at`, `expires_at`, `created_at` y `updated_at`.

## Verificación de las formas normales

| Forma | Qué exige | Cómo se cumple |
| --- | --- | --- |
| Primera | Valores atómicos, sin grupos repetidos | Una fila por línea de venta, por línea de entrada y por franja del horario |
| Segunda | Ningún dato depende de una parte de la llave | Las llaves primarias son de una sola columna (`id`) |
| Tercera | Ningún dato depende de otro que no sea llave | El nombre del producto vive en `productos`, no en las líneas; «agotado» y el stock vendible no se guardan, se calculan |

## Excepciones deliberadas

| Excepción | Por qué es correcta |
| --- | --- |
| `negocio_id` se repite en `entrada_lineas` y `venta_lineas` aunque la cabecera ya lo implica | La seguridad a nivel de fila filtra cada tabla por su propia columna |
| `ventas.total` y `venta_lineas.subtotal` se guardan aunque salen de las líneas | Son el hecho histórico de lo que se cobró; el repositorio los escribe en la misma transacción que las líneas |
| La línea de venta guarda `precio_unitario` | No es un dato derivado: es a cuánto se vendió. Cambiar el precio del producto no altera ventas pasadas |
| `productos.stock` se guarda aunque sale de `movimientos_inventario` | Es la fila que se bloquea para vender: sin ella, dos cajas leerían el mismo saldo y venderían la misma unidad. Cada cambio deja su movimiento con el `stock_resultante` |
| `productos.costo_actual` repite el último costo de `entrada_lineas` | Un producto creado a mano no tiene entradas, y el historial de precios conserva cada valor |
| `entrada_lineas.costo_anterior` se guarda | Es la comparación que vio quien recibió la mercadería, aunque después cambie el costo |
| `personal_access_tokens.id` es `bigint` y no `uuid` | La tabla pertenece a Sanctum: el token tiene la forma `<id>\|<secreto>` |

## Regla para cada migración nueva

Antes de fusionar un pull request con una migración se revisa que la tabla tenga llave primaria `id`, que lleve `negocio_id` si pertenece a un negocio, que tenga la seguridad a nivel de fila habilitada y forzada con su política `<tabla>_aislamiento_negocio`, que tenga su `down()`, que no guarde valores calculables sin justificarlo aquí y que este documento quede actualizado.
