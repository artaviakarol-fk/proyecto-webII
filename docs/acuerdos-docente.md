# Acuerdos con el docente

Constancia escrita de lo acordado con el docente del curso ISW-621 Web II para el proyecto **Minisúper Teresita**, del grupo *Las Chicas Superpoderosas* (Karol y Jania).

**Fecha de la conversación:** 30/09/2026

## Acuerdos

| # | Tema | Acuerdo |
| --- | --- | --- |
| 1 | Vertical | Se desarrolla un sistema para un minisúper real, Minisúper Teresita. Las reglas del negocio salen de la entrevista con el dueño. |
| 2 | Extras | Se agregan tres extras a la vertical: fiado con límite por cliente, WhatsApp Business Cloud (consulta de saldo) y Google Calendar (pedidos a proveedores y su entrega). |
| 3 | Factura electrónica | Queda **fuera del alcance** por indicación del docente, por el tiempo que requiere. El dueño la usa en su operación, pero este sistema no la emite ni la administra. |
| 4 | Arquitectura | La API se organiza con arquitectura hexagonal, con la misma estructura del proyecto guía SodaYa-Hexagonal (`src/<Contexto>/<Módulo>/<Capa>`). Está descrita en [`architecture.md`](architecture.md). |

## Dónde quedó reflejado

- La ficha del proyecto ([`Ficha-de-definicion-del-proyecto.docx`](Ficha-de-definicion-del-proyecto.docx)) declara los extras y lista la factura electrónica entre lo que el sistema no hará.
- La estructura del código está documentada en [`architecture.md`](architecture.md).
