# ADR 0010: Cobros con tarjeta y SINPE Móvil mediante un puerto de pasarela

Estado: aceptada.

## Contexto

La Investigación 1 pide cobrar ventas con ONVO Pay (tarjeta y SINPE Móvil) y con PayPal, y poder cambiar de proveedor por configuración. Cada proveedor tiene su propia API, sus propios estados y sus propios errores. Si el caso de uso los conociera, cambiar de proveedor obligaría a reescribirlo.

## Decisión

- Nuevo módulo `Billing/Payments`. El dominio solo conoce `Payment`, `PaymentStatus` (`pendiente`, `pagado`, `fallido`), `PaymentMethod` y `ChargeResult`.
- El puerto `PaymentGateway` (`provider()`, `charge()`, `status()`, `verifyWebhook()`) vive en `Application/Contracts`. Cada proveedor es un adaptador en `Infrastructure/Gateways` que traduce su API a `ChargeResult` (capa anticorrupción). Es el patrón Strategy.
- `PAYMENT_GATEWAY` (`onvo` por defecto) elige el adaptador en `PaymentsServiceProvider`. Un valor desconocido es un error de configuración, no un silencio.
- Los adaptadores usan el cliente HTTP de Laravel directamente, sin SDK: la API es corta, se prueba con `Http::fake()` y no se agrega una dependencia que el equipo no controla. `Http::preventStrayRequests()` en `TestCase` garantiza que ninguna prueba llegue a un proveedor real.
- Solo se aceptan llaves de pruebas (`onvo_test_...`). Ninguna llave real se puede cargar por error en este avance.
- Si el proveedor no responde o devuelve algo inesperado, se lanza `GatewayUnavailableException` y la API responde 503 con `Retry-After`. Un rechazo del banco es otra cosa: 409 con el código de rechazo.
- El monto sale siempre del total de la venta en el servidor (en céntimos, ver ADR 0008), nunca del cliente. Los datos de la tarjeta nunca pasan por la API: el cliente crea el método de pago con ONVO y la API solo recibe su identificador.
- La tabla `pagos` guarda el estado, la referencia del proveedor y una versión reducida de su respuesta (`payload`). Un índice único parcial impide dos pagos vivos para la misma venta.

## Consecuencias

- Agregar un proveedor es una clase nueva y una línea en el `match` del provider.
- El webhook y la marca de la venta como cobrada quedan para el Avance 2; mientras tanto el pago pendiente de SINPE se consulta con `GET /ventas/{venta}/pago`.
- El pago es responsabilidad de `Billing`; para leer la venta usa el caso de uso `GetSale` de `Sales` mediante un puerto propio, sin tocar su dominio.
