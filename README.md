# Minisúper Teresita — API

[![CI](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml/badge.svg)](https://github.com/artaviakarol-fk/proyecto-webII/actions/workflows/ci.yml)

Proyecto del curso **ISW-621 Web II**. API REST en **Laravel (PHP)** para la gestión de un minisúper: inventario, ventas, fiado con límite, proveedores y más. El frontend se hará después con Vue.js.

**Equipo:** Las Chicas Superpoderosas — Karol Artavia (`artaviakarol-fk`) y Jania (`jania02`).

## Estructura del repositorio

| Ruta | Contenido |
|---|---|
| [`api/`](api/) | API REST en Laravel. El código del producto vive en `api/src/`. |
| [`docs/architecture.md`](docs/architecture.md) | Estructura, capas, regla de dependencias, errores y rutas. |
| [`docs/adr/`](docs/adr/) | Decisiones de arquitectura (ADR), una por archivo. |
| [`docs/data-model.md`](docs/data-model.md) | Diagrama y detalle de cada tabla, formas normales y excepciones. |
| [`docs/database.md`](docs/database.md) | Roles de PostgreSQL, migraciones, índices, convenciones y Row Level Security. |
| [`docs/patterns.md`](docs/patterns.md) | Patrones de diseño adoptados, previstos y descartados. |
| [`docs/ubiquitous-language.md`](docs/ubiquitous-language.md) | Glosario del negocio: el mismo término en el código, la base y la API. |
| [`docs/despliegue.md`](docs/despliegue.md) | Despliegue en el VPS con Docker y Caddy. |

## Arquitectura

La API combina **arquitectura hexagonal** (puertos y adaptadores) con diseño guiado por el dominio, siguiendo la misma estructura del proyecto guía del curso, [SodaYa-Hexagonal](https://github.com/BryanChavesSalas/SodaYa-Hexagonal). El código del producto vive en `api/src/`, bajo el namespace `Src\`, organizado por contexto y módulo y, dentro de cada módulo, en tres capas:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

El dominio no depende de Laravel y la prueba `tests/Architecture/LayerDependencyTest.php` lo verifica en cada cambio. El detalle está en [docs/architecture.md](docs/architecture.md).

## Avance 1: qué entregamos y cómo se relaciona con el enunciado

El enunciado está pensado para un sistema de reservas de servicios, pero nuestro proyecto es un minisúper real (lo definimos en la ficha y lo hablamos con el dueño). Por eso adaptamos cada punto del Avance 1 a lo que hace un minisúper:

| El enunciado pide | Lo que hicimos en el Minisúper Teresita | Dónde verlo |
|---|---|---|
| Catálogo con su precio | Catálogo de productos con código, categoría, precio de venta, costo y foto | `GET/POST/PUT/DELETE /productos` |
| Horarios de atención del negocio | El horario de la semana, por día y con hasta 3 tramos (por ejemplo, cerrar al mediodía). También dice si el negocio está abierto ahora, en hora de Costa Rica | `GET/PUT /negocio/horario` |
| Horarios de cada colaborador | No lo hicimos: en la ficha dijimos que el sistema no administra horarios de empleados | — |
| Disponibilidad real | El stock vendible (el stock menos las unidades vencidas) y si el producto está agotado | `GET /productos/{id}/disponibilidad` |
| Reservas con CRUD | Para nosotras las reservas son las **ventas**: se crean (las unidades salen del stock), se listan, se consultan, se modifican mientras están abiertas, se cobran y se anulan (las unidades vuelven al stock) | `/ventas` |
| Control de concurrencia en la reserva | Si dos cajas venden la última unidad al mismo tiempo, una la consigue y la otra recibe un `409`. Para eso bloqueamos el producto en la base de datos | `LastUnitRaceTest` |
| Datos aislados por negocio | El negocio sale del token y PostgreSQL filtra las filas con Row Level Security. Si piden algo de otro negocio, responde `404` | [Sesión y aislamiento](#sesión-y-aislamiento-por-negocio) |
| Documentación con Scramble | Documentación interactiva y el contrato OpenAPI | `/docs/api` y `api/openapi/v1.json` |
| Pruebas con Pest y CI en verde | Pint, Larastan (nivel 8) y Pest corren en cada pull request | `.github/workflows/ci.yml` |
| Sistema desplegado | Un VPS con Docker y Caddy, con HTTPS automático | [Despliegue](#despliegue) |

## Puesta en marcha

Por ahora la API se ejecuta **en local con Docker** (API + PostgreSQL). El frontend se publicará más adelante en GitHub Pages.

Requisitos: Docker Desktop.

```bash
cp .env.example .env      # definir DB_PASSWORD y DB_ADMIN_PASSWORD con contraseñas propias
docker compose up -d --build
docker compose exec api php artisan db:seed   # dos negocios ficticios con sus cuentas
```

La primera vez tarda unos minutos: crea los roles de PostgreSQL, instala dependencias, genera la clave de la app y corre las migraciones.

> **Si ya tenían la base de Docker de antes del aislamiento por negocio**, bórrenla una vez para que se creen los roles nuevos: `docker compose down -v` y luego `docker compose up -d --build`. Se pierden los datos locales, que se recuperan con el `db:seed`.

- API: <http://localhost:8000>
- Ruta de salud: <http://localhost:8000/api/v1/health>
- PostgreSQL de Docker: `localhost:5433` (usa el 5433 para no chocar con un PostgreSQL instalado en la compu)

Para detenerlo: `docker compose down`.

### Despliegue

El sistema se despliega en un VPS con Docker y Caddy (HTTPS automático). La guía paso a paso está en [docs/despliegue.md](docs/despliegue.md).

**Sistema desplegado:**

- API: https://15-235-92-245.sslip.io/api/v1
- Documentación: https://15-235-92-245.sslip.io/docs/api
- Salud: https://15-235-92-245.sslip.io/api/v1/health

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
# Una sola vez, como superusuario: crea los roles minisuper_owner y minisuper_app y las bases
psql -U postgres -v owner_password='...' -v app_password='...' -f database/roles.sql
cp .env.example .env      # poner esas contraseñas en DB_ADMIN_PASSWORD y DB_PASSWORD
php artisan key:generate
php artisan migrate --database=pgsql_admin   # las migraciones las corre el dueño del esquema
php artisan db:seed
php artisan serve
```

Las pruebas (`vendor/bin/pest`) usan la base `minisuper_testing`, que también crea `roles.sql`.

### Sesión y aislamiento por negocio

Cada cuenta pertenece a un negocio. Para usar la API se pide un token y se envía en cada petición:

```bash
curl -X POST http://localhost:8000/api/v1/tokens \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"correo":"admin@teresita.test","contrasena":"password","dispositivo":"Mi compu"}'

curl http://localhost:8000/api/v1/negocio -H 'Authorization: Bearer <token>' -H 'Accept: application/json'
```

El negocio **siempre sale de la cuenta del token**, nunca del cuerpo ni de la URL, y PostgreSQL (Row Level Security) solo deja ver las filas de ese negocio. Un recurso de otro negocio responde `404`, igual que uno que no existe. El detalle está en [docs/architecture.md](docs/architecture.md#aislamiento-por-negocio).

Cuentas de demostración (`php artisan db:seed`, contraseña `password`, datos ficticios):

| Negocio | Correo | Rol |
|---|---|---|
| Minisúper Teresita | `admin@teresita.test` | administrador |
| Minisúper Teresita | `caja@teresita.test` | cajero |
| Minisúper Teresita | `bodega@teresita.test` | bodeguero |
| Minisúper La Esquina | `admin@laesquina.test` | administrador |
| Minisúper La Esquina | `caja@laesquina.test` | cajero |

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
| Aislamiento por negocio y login (#5) | Código y pruebas generados siguiendo el proyecto guía SodaYa-Hexagonal | Revisado en el PR por el equipo; pruebas en verde en CI |
| CRUD de catálogo de productos (#6) | Código y pruebas generados con el mismo patrón | Revisado en el PR por el equipo; pruebas en verde en CI |
| Foto de producto y logo del negocio (#7) | Código y pruebas generados con el mismo patrón | Revisado en el PR por el equipo; pruebas en verde en CI |
| Entradas de mercadería (#8) | Código y pruebas generados con el mismo patrón | Revisado en el PR por el equipo; pruebas en verde en CI |
| Comparar costo y ajustar precio (#9) | Código y pruebas generados con el mismo patrón | Revisado en el PR por el equipo; pruebas en verde en CI |
| Consulta de disponibilidad (#10) | Código y pruebas generados con el mismo patrón | Revisado en el PR por el equipo; pruebas en verde en CI |
| Venta en efectivo con control de concurrencia (#11) | Código y pruebas generados con el mismo patrón | Revisado en el PR por la otra integrante; pruebas en verde en CI; la revisora probó 4 cajas vendiendo la última unidad al mismo tiempo |
| Control de edad en la venta de licor (#12) | Código y pruebas generados con el mismo patrón | Revisado en el PR por la otra integrante; pruebas en verde en CI |
| Unidades vencidas y aviso en caja (#13) | Código y pruebas generados con el mismo patrón | Revisado en el PR por la otra integrante; pruebas en verde en CI; la revisora probó ventas y marcados simultáneos |
| Documentación de la API con Scramble (#15) | Código, configuración y pruebas generados con asistencia de IA | Revisado en el PR por la otra integrante; pruebas en verde en CI; la revisora comprobó que `openapi.json` coincide con lo que genera Scramble |
| Pruebas de caminos críticos (#16) | Pruebas generadas con asistencia de IA | Revisado en el PR por la otra integrante; pruebas en verde en CI |
| Orden de bloqueo en entradas de mercadería (#70) | El problema se encontró y se corrigió con asistencia de IA al revisar las ventas | Revisado en el PR por la otra integrante; pruebas en verde en CI; probado con peticiones simultáneas contra un servidor real |
| Horario de atención del negocio | Código y pruebas generados con asistencia de IA, a partir de los horarios reales del Minisúper Teresita que aportó la integrante | Revisado en el PR por la otra integrante; pruebas en verde en CI |
| Despliegue en el VPS | Archivos de Docker Compose, Caddy y la guía generados con asistencia de IA; la integrante hizo el ingreso al servidor | Revisado en el PR por la otra integrante; la API responde en la URL pública |
| Alineación con SodaYa-Hexagonal | Comparación con el proyecto guía, cambios de código, pruebas y documentos (`docs/adr`, modelo de datos, base de datos, patrones, lenguaje ubicuo) generados con asistencia de IA | Revisado en el PR por la otra integrante; pruebas, Pint y PHPStan nivel 8 en verde en CI |

Esta tabla se actualiza conforme avanza el proyecto. Cada integrante debe poder explicar y modificar cualquier parte del código.
