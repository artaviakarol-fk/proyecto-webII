# ADR 0008: Dinero en céntimos dentro del dominio

Estado: aceptada.

## Contexto

Los precios de un minisúper tienen céntimos (por ejemplo `1050.50` en una factura de proveedor). Un número de punto flotante pierde precisión al sumar líneas. El proyecto guía usa colones enteros porque una soda no cobra céntimos; el minisúper sí los recibe en los costos.

## Decisión

El value object `Money` (`src/Shared/Domain/ValueObjects`) guarda el monto como un entero de céntimos, nunca negativo. Lee y escribe el texto decimal con dos decimales (`fromDecimal`, `toDecimal`) y la base lo guarda en `numeric(12,2)`. La API lo muestra como número con dos decimales.

## Alternativas descartadas

- **`float` en PHP o `double precision` en la base.** Pierde precisión en las sumas.
- **Colones enteros como en SodaYa.** No representa los costos con céntimos que trae la factura.

## Consecuencias

- Totales y subtotales se calculan con enteros y nunca se redondean a medias.
- `MoneyTest` cubre los límites y el formato.
