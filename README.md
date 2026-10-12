# Minisúper Teresita — API

[![CI](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml/badge.svg)](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml)

Proyecto del curso **ISW-621 Web II**. API REST en **Laravel (PHP)** para la gestión de un minisúper: inventario, ventas, fiado con límite, proveedores y más. El frontend se hará después con Vue.js.

**Equipo:** Las Chicas Superpoderosas — Karol Artavia (`artaviakarol-fk`) y Jania (`jania02`).

## Estructura del repositorio

```
api/   → API Laravel (backend)
```

## Arquitectura

La API usa **arquitectura hexagonal** (puertos y adaptadores):

- `Domain`: reglas de negocio puras, sin dependencias de Laravel.
- `Application`: casos de uso y puertos (interfaces).
- `Infrastructure`: adaptadores (Eloquent, HTTP, pasarelas de pago, WhatsApp, etc.).

Una prueba de arquitectura con Pest verificará que `Domain` no dependa de `Illuminate`.

## Puesta en marcha

Requisitos: PHP 8.4 o superior, Composer 2.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Flujo de trabajo

- `main`: versión estable. `develop`: integración.
- Ninguna de las dos recibe commits directos: todo entra por **pull request** con al menos **1 aprobación** de la otra integrante.
- Ramas de trabajo, creadas desde `develop`:

| Prefijo | Uso | Ejemplo |
|---|---|---|
| `feature/` | funcionalidad nueva | `feature/product-crud` |
| `fix/` | corrección de errores | `fix/stock-negative` |
| `chore/` | configuración y mantenimiento | `chore/ci-workflow` |

- Commits en **inglés** con [Conventional Commits](https://www.conventionalcommits.org/): `tipo(alcance opcional): descripción`.
  Tipos: `feat`, `fix`, `chore`, `docs`, `ci`, `test`, `refactor`.
  Ejemplo: `feat(sales): add credit limit validation`.
- Cada PR usa la plantilla de `.github/pull_request_template.md`.

## Variables de entorno

El archivo `.env` **no se sube al repositorio**. Se copia desde `api/.env.example` y cada integrante o servidor define sus propios valores.

## Uso de inteligencia artificial

Este proyecto se desarrolla con asistencia de IA (Claude, de Anthropic). Su uso se declara aquí con honestidad:

| Parte | Cómo se usó la IA | Verificación |
|---|---|---|
| Planificación (milestones, issues, historias de usuario) | Borradores generados a partir de la entrevista con el cliente | Revisados y ajustados por el equipo |
| Configuración inicial del repositorio y CI | Guía paso a paso y propuestas de archivos | Ejecutado y probado por el equipo |

Esta tabla se actualiza conforme avanza el proyecto. Cada integrante debe poder explicar y modificar cualquier parte del código.
