# ADR 0009: Hora de Costa Rica en la aplicación y en la base

Estado: aceptada.

## Contexto

El minisúper atiende en Costa Rica. El horario de atención, el filtro de ventas por día y las fechas de las entradas se entienden en hora local. Si la aplicación trabaja en UTC, «las ventas del 15 de octubre» incluiría ventas del 14 en la noche.

## Decisión

`config/app.php` y las conexiones de `config/database.php` usan `APP_TIMEZONE`, por defecto `America/Costa_Rica`, igual que el proyecto guía. La sesión de PostgreSQL trabaja en la misma zona y las columnas de fecha y hora son `timestamptz`. La API escribe las fechas en ISO 8601 con su desfase (`2026-10-15T09:30:00-06:00`).

## Consecuencias

- `TimeZoneTest` revisa la zona de la aplicación y de la sesión de la base.
- Costa Rica no cambia de horario en el año, así que no hay días de 23 ni de 25 horas.
