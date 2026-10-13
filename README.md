# Minisúper Teresita — API

[![CI](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml/badge.svg)](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml)

Proyecto del curso **ISW-621 Web II**. API REST en **Laravel (PHP)** para la gestión de un minisúper: inventario, ventas, fiado con límite, proveedores y más. El frontend se hará después con Vue.js.

**Equipo:** Las Chicas Superpoderosas — Karol Artavia (`artaviakarol-fk`) y Jania (`jania02`).

## Estructura del repositorio

| Ruta | Contenido |
|---|---|
| [`api/`](api/) | API REST en Laravel. El código del producto vive en `api/src/`. |
| [`docs/architecture.md`](docs/architecture.md) | Estructura, capas, regla de dependencias, errores y rutas. |

## Arquitectura

La API combina **arquitectura hexagonal** (puertos y adaptadores) con diseño guiado por el dominio, siguiendo la misma estructura del proyecto guía del curso, [SodaYa-Hexagonal](https://github.com/BryanChavesSalas/SodaYa-Hexagonal). El código del producto vive en `api/src/`, bajo el namespace `Src\`, organizado por contexto y módulo y, dentro de cada módulo, en tres capas:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

El dominio no depende de Laravel y la prueba `tests/Architecture/LayerDependencyTest.php` lo verifica en cada cambio. El detalle está en [docs/architecture.md](docs/architecture.md).

## Puesta en marcha

Por ahora la API se ejecuta **en local con Docker** (API + PostgreSQL). El frontend se publicará más adelante en GitHub Pages.

Requisitos: Docker Desktop.

```bash
cp .env.example .env      # definir DB_PASSWORD con una contraseña propia
docker compose up -d --build
```

La primera vez tarda unos minutos: instala dependencias, genera la clave de la app y corre las migraciones.

- API: <http://localhost:8000>
- Ruta de salud: <http://localhost:8000/api/v1/health>
- PostgreSQL de Docker: `localhost:5433` (usa el 5433 para no chocar con un PostgreSQL instalado en la compu)

Para detenerlo: `docker compose down`.

### Ruta de salud

`GET /api/v1/health` siempre responde `200` e indica el estado de la aplicación y de la base de datos:

```json
{
  "status": "ok",
  "checks": { "app": "ok", "database": "ok" },
  "checked_at": "2026-10-12T14:00:00-06:00"
}
```

Si la base de datos no responde, `status` es `degraded` y `database` es `unavailable`.

### Errores de la API

Todos los errores se devuelven en formato **RFC 9457** (`application/problem+json`), con `type`, `title`, `status`, `detail` e `instance`, y con los mensajes en español desde `api/lang/es`. Los errores de validación incluyen además `errores` por campo. Cada respuesta lleva el encabezado `X-Request-Id`, que se repite en `instance`. La tabla completa está en [docs/architecture.md](docs/architecture.md#errores).

### Sin Docker

Requisitos: PHP 8.4 o superior, Composer 2 y PostgreSQL.

```bash
cd api
composer install
cp .env.example .env      # ajustar DB_* a tu PostgreSQL local
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
