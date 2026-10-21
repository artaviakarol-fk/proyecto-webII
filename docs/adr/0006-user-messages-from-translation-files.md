# ADR 0006: Mensajes al usuario desde los archivos de traducción

Estado: aceptada.

## Contexto

El código está en inglés y la API responde en español. Si los mensajes se escriben dentro de las excepciones del dominio, el dominio queda atado a un idioma y los textos se repiten.

## Decisión

Las excepciones del dominio llevan una clave (`shared.amount_out_of_range`, `sales.insufficient_stock`) y, si hace falta, sus parámetros. `ProblemDetailsRenderer` traduce la clave con `lang/es/<contexto>.php` al armar el documento de problema RFC 9457. Los mensajes de validación salen de `lang/es/validation.php`.

## Alternativas descartadas

- **Textos en español dentro del dominio.** Mezcla idioma y regla.
- **Códigos numéricos de error.** Obligan al cliente a mantener su propia tabla de mensajes.

## Consecuencias

- Todos los textos que ve el usuario están en un solo lugar por contexto.
- `LocalizationTest` revisa que el idioma por defecto sea español y que los mensajes de validación salgan de `lang/es`.
