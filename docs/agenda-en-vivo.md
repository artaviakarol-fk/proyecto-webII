# Agenda en vivo (Práctica 1 · Clase 21)

> Texto del issue «Agenda en vivo». La agenda de nuestro minisúper son las **reservas** de los clientes (las unidades que aparta un cliente por 15 minutos antes de pagar), que la caja y la administración ven al instante.

## 1. Qué necesita tiempo real y qué no

| Pantalla | ¿Tiempo real? | Por qué |
|---|---|---|
| Agenda de reservas de la caja y la administración | **Sí (WebSocket)** | Varias pantallas deben ver al instante el mismo cambio: una reserva nueva o una que se soltó o venció cambian lo que se puede vender. |
| Catálogo público de productos | No | Se consulta cuando la persona entra; un WebSocket sumaría un servidor y un proceso sin ganar nada. |
| Historial de ventas y reportes del cierre | No | Se consultan al abrir la pantalla. |
| Estado del pago de una compra | No (sondeo con tope) | Cambia una sola vez y la cliente ya está esperando la respuesta (clase 18). |

## 2. Canal privado y eventos

- **Canal:** `private-negocio.{negocio_id}.agenda` (en `routes/channels.php` la regla va sin el prefijo `private-`).
- **Quién escucha:** cuentas activas del mismo negocio que el token, con rol administrador, cajero o supervisor. El id del canal se compara con `negocio_id` de la cuenta del token, nunca con algo que mande el navegador. Un cliente o el personal de otro negocio recibe `403`.

| Evento (`broadcastAs`) | Cuándo | Carga exacta (`broadcastWith`) |
|---|---|---|
| `reserva.creada` | Un cliente aparta unidades (`POST /reservas`), después del commit | `{ id, producto, cantidad, expira_en }` |
| `reserva.estado` | Un cliente suelta una reserva (`DELETE /reservas/{id}`) | `{ id, estado }` con `estado = cancelada` |

Ninguna carga lleva nombre, correo ni teléfono del cliente. Los eventos viajan por la cola (`ShouldBroadcast`) y solo salen después del commit (`ShouldDispatchAfterCommit`). Si una reserva se rechaza (por ejemplo, no hay unidades), no se anuncia nada.

Fuera de esta entrega (queda para el 07/12): `reserva.estado` también cuando una reserva vence o se confirma al pagar (`BuyReservations`), y el canal de presencia para ver quién atiende la caja.

## 3. Si se cae la conexión (sondeo y resincronización)

El WebSocket solo avisa que algo cambió; la verdad está en la API. Los eventos que se mandan mientras la pantalla está desconectada se pierden (Reverb no guarda historial). Por eso la pantalla de la caja:

1. **Vigila el estado de la conexión** de Echo (`connected`, `unavailable`, `failed`, `disconnected`).
2. **Sin conexión:** muestra «Sin conexión en vivo» y consulta la API **cada 15 segundos** (`useSondeo` con tope).
3. **Al volver la conexión:** deja de sondear y **recarga la lista completa una vez** desde la API, para recuperar lo que se perdió.
4. Un evento `reserva.creada` no agrega la reserva con la carga del evento: la pantalla vuelve a pedir la lista (el evento es el timbre, la API es la verdad).

La implementación del cliente (Vue + Laravel Echo) se califica el 07/12.

## Prueba de autorización

`tests/Feature/Inventory/Reservations/AgendaChannelTest.php` cubre: personal del negocio `200`, personal de otro negocio `403`, cliente `403` y sin token `401`, con `BROADCAST_CONNECTION=reverb` en `phpunit.xml`. Si se cambia la regla a `return true;`, las pruebas de `403` fallan: así se sabe que la prueba sirve.
