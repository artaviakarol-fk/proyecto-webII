# Arquitectura de la API del Minisúper Teresita

La API combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio, con la misma estructura del proyecto guía del curso, [SodaYa-Hexagonal](https://github.com/BryanChavesSalas/SodaYa-Hexagonal). El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

## Estructura

Todo el código del producto vive en `api/src/`, bajo el namespace `Src\`. La forma es siempre contexto, módulo y capa:

```
src/
├── Shared/                      Núcleo compartido entre contextos
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── <Contexto>/                  Por ejemplo, Inventory
    ├── Shared/                  Lo común a los módulos del contexto
    └── <Módulo>/                Un agregado; por ejemplo, Products
        ├── Domain/
        │   ├── Entities/
        │   ├── ValueObjects/
        │   ├── Contracts/       Puertos (interfaces)
        │   └── Exceptions/
        ├── Application/
        │   ├── UseCases/
        │   └── DTOs/
        └── Infrastructure/
            ├── Http/            Controllers, Requests, Resources y routes.php
            ├── Persistence/     Models, Repositories y Queries
            └── <Módulo>ServiceProvider.php
```

Un módulo corresponde a un agregado, no a una tabla. Un módulo de solo lectura no tiene capa `Domain`: su puerto vive en `Application/Contracts`.

Las carpetas `app/`, `bootstrap/`, `config/`, `database/`, `lang/` y `routes/` conservan su función en Laravel. `app/` solo contiene el arranque del framework (`AppServiceProvider`). Las migraciones, las fábricas y los sembradores se quedan en `database/`.

### Contextos previstos

Propuesta a partir de la ficha del proyecto; cada uno se crea cuando llegue su primer issue.

| Contexto | Módulos | Reglas que concentra |
| --- | --- | --- |
| `Inventory` | `Products`, `Batches` | Stock, lotes con fecha de vencimiento, bloqueo de productos vencidos |
| `Sales` | `Sales`, `CashShifts` | Venta en caja, control de edad para licor, cierre y cuadre de caja |
| `Credit` | `Customers` | Fiado con límite por cliente |
| `Identity` | `Users` | Cuentas y roles: administrador, cajero y bodeguero |

## Regla de dependencias

Las dependencias apuntan siempre hacia adentro:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

| Capa | Puede depender de | No puede depender de |
| --- | --- | --- |
| Domain | PHP y `Shared\Domain` | Application, Infrastructure, Laravel |
| Application | Domain | Infrastructure, Laravel |
| Infrastructure | Application, Domain, Laravel | — |

La prueba `tests/Architecture/LayerDependencyTest.php` revisa cada archivo de `Domain` y `Application` y falla si alguno rompe la regla. También falla si el dominio de un módulo importa el dominio de otro módulo.

## Qué va en cada capa

| Capa | Contenido |
| --- | --- |
| Domain | Entidades y agregados, objetos de valor, excepciones del dominio e interfaces de los puertos (por ejemplo, un repositorio). |
| Application | Un caso de uso por clase, con un único método público `execute()`. Recibe los puertos por constructor y no conoce HTTP ni Eloquent. Los DTO llevan datos primitivos. |
| Infrastructure | Controladores, Form Requests, API Resources, rutas, modelos Eloquent, implementaciones de los puertos y el service provider del módulo. |

Convenciones de nombres: las interfaces, los objetos de valor, los DTO y los casos de uso no llevan sufijo de tipo (`ProductRepository`, `Price`, `RegisterSale`). Las excepciones terminan en `Exception` y los modelos Eloquent en `Model`. Un agregado se crea con `create()` y se rearma desde la base con `reconstitute()`; su constructor es privado. Todo archivo empieza con `declare(strict_types=1);`.

## Flujo de una petición

1. La ruta del módulo (`Infrastructure/Http/routes.php`) entrega la petición a un controlador.
2. Un Form Request valida la forma de los datos antes de llegar al controlador.
3. El controlador invoca un caso de uso con un DTO de datos primitivos.
4. El caso de uso trabaja con el dominio y con los puertos.
5. El adaptador de persistencia traduce entre el dominio y Eloquent.
6. Un API Resource construye la respuesta y expone solo los campos permitidos.

## Puertos y adaptadores

Un puerto es una interfaz definida en `Domain/Contracts`. Su adaptador vive en `Infrastructure` y se enlaza en el service provider del módulo, que se registra en `bootstrap/providers.php`. Cambiar de proveedor (por ejemplo, de pasarela de pago o de WhatsApp) afecta a un solo adaptador.

Cuando dos contextos necesitan colaborar lo hacen por un puerto declarado en el contexto que lo necesita, con un adaptador de infraestructura que llama al caso de uso del otro contexto. Nunca se importa el dominio del otro.

### Ejemplo actual: la ruta de salud

`Src\Shared` ya sigue esta forma:

| Pieza | Archivo |
| --- | --- |
| Puerto | `Shared/Domain/Contracts/DatabaseStatusChecker.php` |
| Caso de uso | `Shared/Application/UseCases/CheckHealth.php` |
| DTO | `Shared/Application/DTOs/HealthReport.php` |
| Adaptador | `Shared/Infrastructure/Persistence/DatabaseConnectionStatusChecker.php` |
| Controlador y respuesta | `Shared/Infrastructure/Http/Controllers/HealthController.php`, `Http/Resources/HealthResource.php` |
| Enlace del puerto | `Shared/Infrastructure/SharedServiceProvider.php` |

### El tiempo es un parámetro

El dominio y la aplicación nunca preguntan la hora. El momento actual se crea en el borde HTTP con `now()` y viaja como `DateTimeImmutable` hasta el caso de uso (`CheckHealth::execute($now)`). Así las reglas que dependen del reloj, como el vencimiento de un lote, se prueban con momentos fijos sin arrancar el framework.

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`.

| Ruta | Qué hace |
| --- | --- |
| `GET /api/v1` | Identifica la API y su versión |
| `GET /api/v1/health` | Estado de la aplicación y de la base de datos |
| `GET /up` | Ruta de salud del framework |

## Pruebas

| Carpeta | Alcance |
| --- | --- |
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. Usan dobles de los puertos de `tests/Support`. |
| `tests/Feature` | Endpoints y adaptadores. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos. |

Las pruebas se ejecutan con Pest (`vendor/bin/pest`), que también corre las pruebas escritas como clases de PHPUnit.
