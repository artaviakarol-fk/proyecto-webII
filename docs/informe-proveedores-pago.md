# Informe comparativo de proveedores de pago: ONVO Pay y PayPal

Investigación 1 del proyecto Minisúper Teresita (ISW-621 Web II). Comparamos las dos pasarelas que integramos en la API, para justificar cuál usamos por defecto y por qué el sistema las deja intercambiables con la variable `PAYMENT_GATEWAY`.

Todo lo que dice este informe, salvo la sección 9, sale de la documentación oficial de cada proveedor, consultada el 25/10/2026. La sección 9 cuenta lo que vimos al integrar. Lo que la documentación no dice lo marcamos como **por confirmar** y no lo damos por cierto. En ningún momento se procesa dinero real: usamos solo llaves y cuentas de prueba.

## 1. Resumen

| Criterio | ONVO Pay | PayPal |
| --- | --- | --- |
| Moneda de cobro | Colones (`CRC`) | No confirmamos `CRC`; cobramos en `USD` |
| Métodos | Tarjeta y SINPE Móvil | Tarjeta (y cuenta PayPal) |
| Comisión | **Por confirmar** (no está publicada) | 5,40 % + USD 0,30 por transacción comercial en Costa Rica |
| Integración | API REST con llave secreta como bearer | API REST con OAuth 2.0 (Orders v2) |
| Confirmación del pago | Webhook con secreto compartido | Webhook con firma verificable |
| Pruebas | Llaves `onvo_test_` y tarjetas y números simulados | Sandbox con cuentas de prueba |

## 2. Comisiones

**PayPal.** La página oficial de comisiones para Costa Rica indica, para cobros comerciales, 5,40 % más una tarifa fija por transacción (USD 0,30 cuando se recibe en dólares). Con una cuenta de negocio aprobada baja por tramos según lo recibido el mes anterior: 5,40 % hasta USD 3.000, 4,90 % hasta USD 10.000, 4,70 % hasta USD 100.000 y 4,40 % por encima. Si hay conversión de moneda se suma un recargo de 3,50 % sobre el tipo de cambio base al recibir pagos.

**ONVO Pay.** No encontramos una tarifa publicada en su documentación técnica. La sección de precios de su API (`Prices`) se refiere a los precios de los productos que define el comercio, no a lo que cobra ONVO. **Por confirmar** con ONVO antes de cualquier decisión comercial.

**Lectura para el minisúper.** Con tickets pequeños, la tarifa fija de PayPal pesa más. Esto es un argumento a favor de ONVO, pero solo se sostiene cuando conozcamos su comisión real.

## 3. Métodos de pago

- **ONVO Pay:** tarjeta y **SINPE Móvil**. SINPE Móvil es el medio que más usan en Costa Rica y funciona solo en colones.
- **PayPal:** tarjeta y cuenta PayPal. No ofrece SINPE Móvil.

Esto cubre lo que pide la ficha del proyecto: cobrar con tarjeta o SINPE Móvil además del efectivo.

## 4. Facilidad de integración

Ambas se consumen por HTTP con el cliente `Http` de Laravel, sin SDK, detrás de un puerto propio (`PaymentGateway`), igual que el proyecto guía SodaYa-Hexagonal.

**ONVO Pay** (base `https://api.onvopay.com/v1`):

1. Crear el método de pago (`POST /payment-methods`).
2. Crear la intención de pago (`POST /payment-intents`) con `amount` en la unidad mínima y `currency`.
3. Confirmar la intención (`POST /payment-intents/{id}/confirm`) con el `paymentMethodId`.
4. Esperar el webhook con el estado final.

Los montos van en la unidad mínima de la moneda: `500000` son ₡5.000,00. Eso coincide con cómo guardamos el dinero (`Money`, en céntimos). Estados de la intención: `requires_payment_method`, `requires_action`, `processing`, `succeeded` y `canceled`. Los datos de tarjeta deben capturarse en el cliente con la llave pública para reducir el alcance PCI; nuestra API solo maneja la llave secreta.

**SINPE Móvil en ONVO:** al confirmar, la intención pasa a `processing` y la persona debe transferir desde una cuenta cuya identificación coincida con la registrada, por el **monto exacto**. ONVO rechaza la transferencia si no hay una intención en `processing` con la misma identificación y monto; no acepta pagos parciales ni mayores. Si el pago nunca llega, la documentación **no indica** cuánto tiempo queda en `processing`. **Por confirmar** con soporte.

**PayPal** (Orders v2; sandbox `https://api-m.sandbox.paypal.com`, producción `https://api-m.paypal.com`):

1. Obtener un token OAuth 2.0 con las credenciales de la aplicación.
2. Crear la orden.
3. La persona la aprueba y se captura el pago.

Todas las llamadas que crean o cambian datos aceptan el encabezado `PayPal-Request-Id`: PayPal lo guarda hasta 45 días y un reintento con el mismo valor devuelve el resultado original en lugar de repetir la acción.

**Moneda en PayPal.** Su página de comisiones para Costa Rica lista las tarifas fijas por moneda y el colón no figura entre ellas. No pudimos confirmar si se puede cobrar en `CRC`. Por eso la integración cobra en `USD` y convierte desde colones con un tipo de cambio configurable. **Por confirmar** si PayPal acepta `CRC` en la cuenta real.

## 5. Webhooks

| | ONVO Pay | PayPal |
| --- | --- | --- |
| Eventos útiles | `payment-intent.succeeded`, `payment-intent.failed`, `payment-intent.deferred` | `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `CHECKOUT.ORDER.APPROVED` |
| Contenido | `type` y `data` con el `id` de la intención | Evento completo con su `id` |
| Verificación de origen | Encabezado `X-Webhook-Secret` con un secreto compartido | Firma con los encabezados `PAYPAL-TRANSMISSION-*`, verificable con `POST /v1/notifications/verify-webhook-signature` |
| Reintentos | La documentación **no los describe** (por confirmar) | Hasta 25 veces en 3 días, según la guía de Hookdeck |

**ONVO** solo documenta un secreto compartido, no una firma HMAC, y el webhook trae únicamente el `id`. Por eso, al recibirlo, volvemos a consultar la intención en ONVO antes de dar la venta por pagada: nunca confiamos solo en el cuerpo que llega.

**PayPal** permite comprobar criptográficamente que el aviso es suyo. Es la verificación más fuerte de las dos.

En ambas, el estado del pago lo decide el webhook verificado y no el navegador. Los reintentos y los eventos fuera de orden exigen que el procesamiento sea **idempotente**; eso se refuerza en el Avance 2.

## 6. Seguridad

- Las llaves viven en `api/.env`, que no se sube al repositorio. El repositorio incluye `.env.example` con los nombres vacíos.
- El adaptador rechaza llaves que no sean de prueba mientras dure el curso (en ONVO, las que no empiezan con `onvo_test_`).
- La API nunca recibe datos de tarjeta: se capturan en el cliente con la llave pública de cada proveedor.
- Las llamadas salen con tiempo de espera de 10 segundos. Si el proveedor no responde, la API contesta `503` con el formato RFC 9457 y `Retry-After`.
- Las pruebas no llaman a ningún servicio real; usan pasarelas simuladas.

## 7. Disponibilidad en Costa Rica

- **ONVO Pay** opera en colones y con SINPE Móvil, que son los medios habituales del comercio local.
- **PayPal** tiene página de comisiones propia para Costa Rica, pero el cobro en colones no está confirmado y recibir en dólares implica conversión.

## 8. Conclusión y recomendación

Recomendamos **ONVO Pay como pasarela por defecto** para el Minisúper Teresita:

1. Cobra en colones, sin conversión de moneda.
2. Ofrece SINPE Móvil, que sus clientes ya usan.
3. Encaja con el modelo de dinero del sistema (céntimos).

Mantenemos **PayPal como alternativa** por tres razones: su verificación de webhooks es más robusta, es útil si un cliente paga desde el exterior y demuestra que la arquitectura permite cambiar de proveedor solo con `PAYMENT_GATEWAY`.

**Riesgos que dejamos abiertos:**

| Tema | Qué falta confirmar | Con quién |
| --- | --- | --- |
| Comisión de ONVO | Porcentaje y tarifa fija | ONVO |
| Reintentos del webhook de ONVO | Cantidad y espaciado | ONVO |
| Vencimiento de un SINPE en `processing` | Cuánto espera ONVO la transferencia | ONVO |
| Moneda en PayPal | Si acepta `CRC` en una cuenta de Costa Rica | PayPal |
| PayPal contra el sandbox real | No se pudo probar: la cuenta de desarrollador pidió una tarjeta (ver sección 9) | Equipo |

## 9. Experiencia real de la integración

Pruebas hechas el 26/10/2026 con llaves de prueba, sin dinero real. Usamos nuestro propio adaptador (`OnvoGateway`) contra el sandbox de ONVO. Cada medida es de una sola ejecución, desde una computadora en Costa Rica, y sirve solo como orden de magnitud.

### ONVO Pay (probado en el sandbox real)

| Prueba | Resultado |
| --- | --- |
| Crear el método de pago con la llave pública y la tarjeta `4242 4242 4242 4242` | HTTP 201. Bastaron número, vencimiento, CVV y nombre del titular; no pidió datos de facturación |
| Crear la intención de cobro por ₡1 500 (`amount: 150000`) y confirmarla | Estado `succeeded`; ONVO devolvió el mismo monto y `CRC`. Tardó unos 1,9 s en total (dos llamadas) |
| Consultar el estado después | `succeeded`, igual que en la confirmación |
| Cobro con la tarjeta de rechazo `4000 0000 0000 0002` | Ver el hallazgo siguiente. Tardó unos 1,4 s |

**Diferencias entre la documentación y el comportamiento real**

1. **Un rechazo no es un error HTTP.** Esperábamos un 400 con un código `cards.*`. En el sandbox, confirmar con la tarjeta de rechazo respondió **HTTP 201** y dejó la intención en `requires_payment_method`. Nuestro adaptador trata ese estado como pago fallido, y además conserva el manejo del 400 `cards.*` que describe la documentación, por si aparece con otras tarjetas.
2. **El motivo del rechazo no venía en la confirmación.** Estaba solo al consultar la intención, en `lastPaymentError` (`code: declined`, mensaje «Transaction declined due to test payment method used»). Primero guardábamos únicamente `requires_payment_method`, que no dice nada útil. Corregimos el adaptador para leer el motivo de esa consulta (el cambio está cubierto por pruebas con respuestas simuladas).
3. **Datos de más.** La intención trae el método de pago completo (marca, últimos cuatro dígitos, vencimiento, titular) y los cargos. Por eso el adaptador guarda solo una lista corta de campos y descarta el resto.
4. **Un detalle del modo de prueba:** el cargo aprobado trae `failureMessage` con el texto de rechazo de prueba aunque `isApproved` es verdadero y `failureCode` está vacío. El mensaje no es confiable para decidir el estado; usamos solo `status`.

**Lo que no pudimos probar:** SINPE Móvil (depende de una transferencia que el sandbox simula con números de prueba y no la ejecutamos) y el webhook (llega en el Avance 2). Siguen valiendo los datos de la documentación y quedan marcados como por confirmar en la sección 8.

### PayPal (no se probó contra el sandbox real)

Para crear una app de pruebas hace falta una cuenta de desarrollador de PayPal y, en Costa Rica, el registro pidió **agregar una tarjeta**. Decidimos no hacerlo: no queríamos poner una tarjeta personal, ni la de una compañera, en un trabajo de clase.

Por eso `PaypalGateway` está escrito a partir de la documentación oficial (Orders v2, OAuth 2.0, `PayPal-Request-Id`, captura de la orden, verificación de firma del webhook) y probado solo con respuestas simuladas. **No podemos afirmar cómo se comporta el sandbox real**: cuánto tarda, qué errores devuelve ni si acepta `CRC`. Por eso cobramos en dólares con un tipo de cambio configurable (`PAYPAL_COLONES_PER_DOLLAR`).

Una diferencia de diseño que sí salió de la documentación: con PayPal el comprador aprueba el pago en una página de PayPal. El cobro queda `pendiente` con una dirección de aprobación y se captura cuando se consulta el pago después. Con ONVO el cliente crea el método de pago y el cobro se resuelve en la misma llamada.

### Qué cambia en la recomendación

Nada: ONVO sigue siendo la pasarela por defecto, y ahora con una integración que funcionó de punta a punta en el sandbox. Para PayPal, lo que queda pendiente es probar contra el sandbox real si alguien del equipo ya tiene una cuenta de desarrollador.

## Fuentes

- ONVO Pay, [Payment intents](https://docs.onvopay.com/en/payments/payment-intents)
- ONVO Pay, [SINPE Móvil](https://docs.onvopay.com/en/payments/sinpe-mobile.md)
- ONVO Pay, [Webhooks](https://docs.onvopay.com/en/webhooks)
- ONVO Pay, [Pruebas](https://docs.onvopay.com/en/payments/testing.md)
- PayPal, [Comisiones para comercios en Costa Rica](https://www.paypal.com/cr/webapps/mpp/merchant-fees)
- PayPal, [Solicitudes a la API REST](https://developer.paypal.com/api/make-api-requests.md)
- Hookdeck, [Guía de webhooks de PayPal](https://hookdeck.com/webhooks/platforms/guide-to-paypal-webhooks-features-and-best-practices)
