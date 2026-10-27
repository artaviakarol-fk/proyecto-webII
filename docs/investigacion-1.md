# Investigación 1: cobro con dos pasarelas y comparación de proveedores

Minisúper Teresita · ISW-621 · Karol Artavia y Jania Hernández.

Se cobra una **venta abierta** y el monto sale del servidor. ONVO y PayPal están integrados detrás de `PaymentGateway` (`docs/adr/0010`); Tilopay y GreenPay se analizan con su documentación.

**Ejemplo de costos del enunciado:** 100 cobros de ₡15.000 al mes = **₡1.500.000**. Para pasar dólares a colones usamos el tipo de cambio de referencia de venta del BCCR del 26/10/2026, **₡456,32** ([Telediario, con dato del BCCR](https://www.telediario.cr/nacional/dolar-costa-rica-tipo-cambio-lunes-26-octubre), consultado el 26/10/2026). Suponemos una liquidación por semana (4 al mes) donde el proveedor la cobra.

## 1. Credenciales de prueba

| Proveedor | Qué se necesita | Tiempo |
| --- | --- | --- |
| ONVO Pay | Cuenta con correo y contraseña; entrega llaves `onvo_test_` en el panel ([Pruebas, ONVO](https://docs.onvopay.com/en/payments/testing.md), 25/10/2026) | Minutos; las obtuvimos el mismo día |
| PayPal | Cuenta de desarrollador; se crea una app REST y se obtienen Client ID y Secret de sandbox, con una cuenta compradora y una vendedora ([PayPal Developer](https://developer.paypal.com/docs/multiparty/create-account/), 23/10/2026) | Sin revisión manual, pero en Costa Rica el registro pidió **agregar una tarjeta** (sección 6) |
| Tilopay | La guía del SDK no publica un sandbox ni credenciales de prueba; remite a soporte@tilopay.com ([SDK, Tilopay](https://tilopay.com/guias/api-y-sdk/sdk), 26/10/2026). La afiliación en línea se valida en **hasta 48 horas** ([Tarifas, Tilopay](https://tilopay.com/tarifas), 26/10/2026) | Hasta 2 días, más la respuesta de soporte |
| GreenPay | Sandbox autogestionado en `sandbox-dashboard.greenpay.me`; producción exige estar inscrito en Hacienda, personería y cédula ([FAQ, GreenPay](https://greenpay.me/preguntas-frecuentes/), 23/10/2026) | Sandbox inmediato; producción hasta 3 días hábiles |

## 2. Costo real para 100 ventas de ₡15.000

| Proveedor | Tarifa usada | Fijos | Total mensual | % de lo vendido |
| --- | --- | --- | --- | --- |
| ONVO (tarjeta) | 3,9 % + USD 0,35 | Liquidación USD 3 × 4 | **₡79.947** | 5,3 % |
| ONVO (todo por SINPE) | 1,5 % | Liquidación USD 3 × 4 | **₡27.976** | 1,9 % |
| PayPal | 5,40 % + USD 0,30 | Retiro en USD: 1 % (mín. USD 10) | **₡108.743** | 7,2 % |
| Tilopay (tarjeta) | 4,25 % + USD 0,35 | Sin mensualidad | **₡79.721** | 5,3 % |
| Tilopay (todo por SINPE) | 2 % + USD 0,35 | Sin mensualidad | **₡45.971** | 3,1 % |
| GreenPay (3,8 %) | 3,8 % | USD 30 + IVA al mes; liquidación USD 2 × 4 | **₡76.120** | 5,1 % |
| GreenPay (4,8 %) | 4,8 % | Igual | **₡91.120** | 6,1 % |

**Cómo se calculó cada uno:**

- **PayPal.** No admite el colón como moneda de cobro ([Currency codes](https://developer.paypal.com/reference/currency-codes/), 23/10/2026), así que se cobra en dólares: USD 3.287,17 al mes, USD 32,87 por venta. La tarifa para cuentas de Costa Rica es 5,40 % + USD 0,30 ([Comisiones PayPal CR, actualizada el 28/09/2026](https://www.paypal.com/cr/webapps/mpp/merchant-fees), 23/10/2026): USD 207,51. El retiro en dólares a un banco de Costa Rica cuesta 1 % del neto: USD 30,80. Retirar en colones suma 3,5 % de conversión.
- **ONVO.** Página oficial ([Precios, ONVO](https://onvopay.com/pricing), 26/10/2026): tarjeta 3,9 % + USD 0,35, SINPE Móvil 1,5 %, sin costo de instalación ni mensualidad en e-commerce, liquidación USD 3.
- **Tilopay.** Página oficial: sin mensualidad; no publica el costo de liquidar.
- **GreenPay.** La FAQ oficial da setup de USD 150 (≈ ₡68.448, una sola vez), mensualidad de USD 30 + IVA (incluye 200 transacciones) y USD 2 por liquidación, pero **no publica el porcentaje**. Los terceros discrepan: 3,8 % según [elcolectivo506.com](https://elcolectivo506.com/pasarelas-de-pago-en-costa-rica-todo-lo-que-debo-saber/) y 4,8 % según [elperiodicocr.com](https://elperiodicocr.com/transando-dinero-digital-en-costa-rica-como-empezar-a-cobrar-por-internet-a-sus-clientes/) (23/10/2026). Por eso damos los dos escenarios. Además retiene 5,31 % de IVA y 1,76 % de renta; no es costo porque se acredita ante Hacienda, pero reduce el dinero que llega.

**Discrepancias con terceros.** Para ONVO, [ecommercecr.com](https://ecommercecr.com/las-mejores-pasarelas-de-pago-en-costa-rica-para-ecommerce-2026/) publica USD 0,25 fijos en tarjeta; la página oficial dice USD 0,35. Para Tilopay, ecommercecr.com publica 4,5 % + USD 0,35 en tarjeta y 1,5 % en SINPE; la página oficial dice 4,25 % + USD 0,35 y 2 % + USD 0,35, y **vale la oficial**. Para PayPal, el blog [crie.cr](https://crie.cr/?p=23497) habla de 3,99 % + USD 0,30, contra 5,40 % de la tabla oficial.

## 3. SINPE Móvil

- **ONVO:** sí. Al confirmar, la intención queda en `processing` y la persona transfiere el **monto exacto** desde una cuenta con la identificación registrada; no acepta montos parciales ni mayores ([SINPE Móvil, ONVO](https://docs.onvopay.com/en/payments/sinpe-mobile.md), 25/10/2026).
- **Tilopay:** sí. Entrega un número, el monto exacto y un código que va en la descripción de la transferencia; solo empareja órdenes creadas en los últimos 25 minutos ([getSinpeMovil, Tilopay](https://tilopay.com/en/developers/sdk/reference/get-sinpe-movil), 26/10/2026).
- **PayPal:** no lo ofrece.
- **GreenPay:** no confirmado. La FAQ solo menciona tarjetas; su LinkedIn habla de "transferencias SINPE", sin documentación técnica.

En el código, cada pasarela lo declara con `supportedMethods()`: ONVO devuelve tarjeta y SINPE Móvil, PayPal solo tarjeta. Pedir SINPE con PayPal responde **422** con un mensaje claro, antes de guardar nada o llamar al proveedor.

## 4. Recibir y retirar el dinero en Costa Rica

| Proveedor | Vía |
| --- | --- |
| ONVO | Liquidación diaria, semanal, bisemanal o mensual a la cuenta bancaria, USD 3 cada una |
| PayPal | Saldo en PayPal y retiro a un banco de Costa Rica (₡625 en CRC, o 1 % mínimo USD 10 en USD) o de EE. UU. (3 %) |
| Tilopay | A cualquier cuenta de cualquier entidad, con la frecuencia que el comercio elija |
| GreenPay | A una cuenta IBAN local en colones o dólares: diaria (T+1), semanal, quincenal o mensual |

## 5. Cómo verifica cada proveedor que una notificación es suya

Ordenados del más robusto al más débil:

1. **PayPal: firma asimétrica.** Cada webhook trae los encabezados `PAYPAL-TRANSMISSION-ID`, `-TIME`, `-SIG`, `PAYPAL-CERT-URL` y `PAYPAL-AUTH-ALGO`. PayPal firma con RSA-SHA256 la cadena `id|hora|webhook_id|CRC32(cuerpo)`. Se verifica con su certificado público o con `POST /v1/notifications/verify-webhook-signature` ([PayPal](https://docs.paypal.ai/reference/api/rest/verify-webhook-signature/verify-webhook-signature), 23/10/2026). Es la más sólida: no hay secreto compartido que se pueda filtrar, y la firma cubre contenido, `webhook_id` y hora, así que detecta mensajes alterados o repetidos. Hay que validar que `PAYPAL-CERT-URL` sea de un dominio de PayPal.
2. **ONVO: secreto compartido.** Envía el secreto en el encabezado `X-Webhook-Secret` ([Webhooks, ONVO](https://docs.onvopay.com/en/webhooks), 25/10/2026). No firma el cuerpo: quien obtenga el secreto puede fabricar cualquier aviso, y un aviso alterado no se detecta. Nuestro adaptador lo compara en tiempo constante (`hash_equals`).
3. **Tilopay: `orderHash` con algoritmo no público.** La documentación dice que su algoritmo no es público, que hay que pedirlo a soporte, y recomienda confirmar el estado por la API antes de decisiones irreversibles ([Webhooks, Tilopay](https://tilopay.com/en/developers/api/webhooks), 26/10/2026). No se puede auditar ni implementar sin esa respuesta.
4. **GreenPay: sin mecanismo documentado.** El webhook del checkout envía un JSON con `status`, `orderId` y autorización, sin firma ni secreto ([Checkoutform webhook](https://support.greenpay.me/portal/es/kb/articles/checkoutform-webhook), 23/10/2026). Cualquiera que conozca la URL podría enviar `"status": 200`.

En esta entrega no hay webhooks (Avance 2): el estado **siempre se consulta al proveedor**, nunca al navegador.

## 6. Qué integración costó más, con hechos

**ONVO** (Karol, 26/10/2026, sandbox real):

- Cobro aprobado con `4242 4242 4242 4242` por ₡1.500 (`amount: 150000`): unos 1,9 s en total.
- **La documentación no coincidió con el sandbox.** Un rechazo con `4000 0000 0000 0002` no devolvió un 400 `cards.*`, sino **HTTP 201** con la intención en `requires_payment_method`.
- **El motivo del rechazo no venía en la confirmación,** solo al volver a consultar la intención (`lastPaymentError`). Hubo que corregir el adaptador.
- **SINPE Móvil** con el número de prueba `+50688888888`: el cobro quedó `pendiente` y pasó a `pagado` a los 15 s, como dice la documentación.

**PayPal** (Karol, 26/10/2026, sandbox real):

- Al registrar la cuenta en Costa Rica, PayPal pidió **agregar una tarjeta**; no vinculamos ninguna y al día siguiente el panel abrió sin pedirla.
- Crear la orden de ₡3.000 (USD 6,00) tardó unos **7,7 s**. Después de aprobarla con la cuenta compradora, capturarla tardó unos **4,1 s** y la orden quedó `COMPLETED`. ONVO respondió en 1,4 a 1,9 s.

**PayPal fue la más difícil:** el tiempo se fue en aprobación, captura y conversión de moneda; en ONVO, en las diferencias entre documentación y sandbox.

## 7. Qué pasa con una venta cuando el proveedor no responde

- **Tiempo límite.** Cada llamada tiene `timeout` de 10 s.
- **Al cobrar.** Si se agota o el proveedor responde con error, se lanza `GatewayUnavailableException`, la transacción se revierte y **no se guarda ningún pago**. La API responde **503** (RFC 9457) con `Retry-After: 30` y la venta sigue abierta para reintentar.
- **No se reintenta el cobro,** porque el proveedor pudo haberlo recibido y un reintento cobraría dos veces. Para cubrir ese caso:
  - Un índice único parcial impide dos pagos vivos por venta.
  - PayPal recibe `PayPal-Request-Id`, así un reintento devuelve la misma orden.
- **Al consultar.** Leer el estado sí se reintenta hasta 3 veces. Si el proveedor sigue caído, el pago conserva su estado guardado (por ejemplo, `pendiente`).

**Pago y venta van juntos.** Cuando un pago queda `pagado`, la venta pasa a `cobrada` en la misma transacción, con su forma de pago; si algo falla, se revierte todo. Un reembolso anula la venta y devuelve el inventario.

**Reembolso en PayPal: por qué no se implementó.** Se haría con `POST /v2/payments/captures/{capture_id}/refund`, pero exige guardar el id de la captura (hoy guardamos el de la orden) y el monto volvería en dólares con otro tipo de cambio. El enunciado solo lo exige en ONVO. El adaptador lanza `RefundNotSupportedException` y el reembolso se hace desde el panel de PayPal.

## 8. Recomendación

**ONVO Pay como pasarela principal**, por cuatro razones:

- Cobra en colones, sin conversión.
- Ofrece SINPE Móvil, el medio que más usan los clientes del minisúper; con SINPE es la más barata del cuadro (≈ 1,9 %).
- Ya funcionó de punta a punta en el sandbox.
- Permite reembolsar desde el sistema.

**PayPal queda como respaldo:** sirve para clientes en el exterior, tiene la verificación de notificaciones más robusta y demuestra que el cambio de proveedor es solo `PAYMENT_GATEWAY` en el `.env`.

**Cambiaríamos de proveedor si:**

- ONVO sube su tarifa por encima de la de Tilopay (4,25 % + USD 0,35). En ese caso Tilopay pasaría a principal, siempre que nos entregue su algoritmo de `orderHash`.
- El volumen supera las 200 ventas mensuales con tarjeta. Ahí conviene renegociar con GreenPay, cuya mensualidad fija se diluye.
- ONVO tiene caídas repetidas. Ahí PayPal entra como respaldo, aceptando el 7,2 % de costo.

## Fuentes adicionales

[Reembolsos, ONVO](https://docs.onvopay.com/en/payments/refunds) (26/10/2026); [Intenciones de pago, ONVO](https://docs.onvopay.com/en/payments/payment-intents) (25/10/2026).
