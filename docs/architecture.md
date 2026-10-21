# Arquitectura de la API del Minisúper Teresita

La API combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio, con la misma estructura del proyecto guía del curso, [SodaYa-Hexagonal](https://github.com/BryanChavesSalas/SodaYa-Hexagonal). El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

Las decisiones están en [`adr/`](adr/), las tablas en [`data-model.md`](data-model.md), las reglas de PostgreSQL en [`database.md`](database.md), los patrones en [`patterns.md`](patterns.md) y el vocabulario en [`ubiquitous-language.md`](ubiquitous-language.md).

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
| `Identity` | `Users` | Cuentas y roles: administrador, cajero y bodeguero. Login con tokens de Sanctum |
| `Negocios` | `Profile`, `OpeningHours` | El negocio (inquilino) al que pertenece cada cuenta y cada dato, y su horario de atención semanal |

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

## Errores

El dominio lanza excepciones que extienden `Src\Shared\Domain\Exceptions\DomainException`. Cada excepción lleva una clave de traducción, no un texto: el mensaje en español se resuelve en la infraestructura desde `lang/es`.

Toda respuesta de error sigue RFC 9457 (`application/problem+json`). `ProblemDetailsRenderer` convierte cada excepción en un documento con `type`, `title`, `status`, `detail` e `instance`:

| Excepción | Tipo de problema | HTTP |
| --- | --- | --- |
| Validación de un Form Request | `datos-invalidos`, con `errores` por campo | 422 |
| `InvalidValueException` | `datos-invalidos` | 422 |
| `NotFoundException` | `no-encontrado` | 404 |
| `AuthenticationFailedException` | `credenciales-invalidas` | 401 |
| Ruta protegida sin sesión (`AuthenticationException` del framework) | `no-autenticado` | 401 |
| Otra excepción del dominio, como un stock insuficiente | `conflicto` | 409 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

Los tipos están en el enum `ProblemType` y sus textos en `lang/es/problems.php`. El campo `instance` repite el identificador de la solicitud, que también viaja en el encabezado `X-Request-Id` (lo asigna el middleware `AssignRequestId`) y en el contexto de cada línea de log.

## Aislamiento por negocio

Varios negocios comparten la misma base de datos. El aislamiento tiene dos capas, igual que en el proyecto guía SodaYa-Hexagonal:

1. **El negocio sale del token.** El middleware `negocio` (`ScopeRequestToNegocio`) toma el `negocio_id` de la cuenta autenticada y abre una transacción con `set_config('app.negocio_id', ...)`. Ningún `negocio_id` del cuerpo, la query string o la URL se lee nunca. En el dominio, el puerto `NegocioContext` entrega ese mismo negocio a los casos de uso.
2. **PostgreSQL filtra las filas (Row Level Security).** Cada tabla con `negocio_id` se protege en su migración con `RowLevelSecurity::enable('tabla')`. La política solo deja leer, insertar, cambiar o borrar filas del negocio fijado en la transacción; sin negocio fijado no se ve ninguna. Por eso un recurso de otro negocio responde `404`, igual que uno que no existe, aunque el código olvide filtrar.

Para que la política se aplique siempre, la aplicación se conecta con un rol sin privilegios (`minisuper_app`): no es superusuario ni dueño de ninguna tabla. Las migraciones las corre el dueño del esquema (`minisuper_owner`, conexión `pgsql_admin`). Los roles se crean con `api/database/roles.sql`.

Una tabla nueva de un negocio se crea así:

```php
Schema::create('productos', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
    // ...
});
RowLevelSecurity::enable('productos');      // en down(): RowLevelSecurity::disable('productos');
```

y sus rutas llevan `->middleware(['auth:sanctum', 'negocio'])`. La prueba `RowLevelSecurityTest` falla si alguna tabla con `negocio_id` (salvo `users`, que se consulta en el login antes de saber el negocio) no tiene su política.

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`.

| Ruta | Qué hace |
| --- | --- |
| `GET /api/v1` | Identifica la API y su versión |
| `GET /api/v1/health` | Estado de la aplicación y de la base de datos |
| `POST /api/v1/tokens` | Login: correo, contraseña y dispositivo → token Bearer con las abilities del rol |
| `GET /api/v1/negocio` | Perfil del negocio de la cuenta autenticada |
| `GET /api/v1/productos` | Catálogo del negocio por páginas (`?buscar=`, `?pagina=`, `?por_pagina=`). Todo el personal |
| `GET /api/v1/productos/{id}` | Un producto del negocio. Todo el personal |
| `POST /api/v1/productos` | Crear un producto. Solo administración |
| `PUT /api/v1/productos/{id}` | Reemplazar los datos de un producto. Solo administración |
| `DELETE /api/v1/productos/{id}` | Borrado lógico de un producto; su código queda libre. Solo administración |
| `POST /api/v1/productos/{id}/foto` | Subir o reemplazar la foto (campo `imagen`, multipart). Solo administración |
| `GET /api/v1/productos/{id}/foto` | Descargar la foto del producto. Todo el personal |
| `POST /api/v1/negocio/logo` | Subir o reemplazar el logo del negocio. Solo administración |
| `GET /api/v1/negocio/logo` | Descargar el logo del negocio de la cuenta |
| `GET /api/v1/negocio/horario` | Horario semanal del negocio y si está abierto ahora. Todo el personal |
| `PUT /api/v1/negocio/horario` | Reemplazar el horario semanal completo. Solo administración |
| `GET /api/v1/productos/{id}/disponibilidad` | Stock, unidades vencidas, stock vendible y si está agotado. Todo el personal |
| `PATCH /api/v1/productos/{id}/precio` | Aceptar un nuevo precio de venta (`precio_venta`). Solo administración |
| `GET /api/v1/productos/{id}/historial-precios` | Historial de costos y precios del producto, del más reciente al más antiguo. Solo administración |
| `POST /api/v1/productos/{id}/vencidos` | Marcar unidades vencidas de un producto (`cantidad`, `motivo`). Siguen en el stock pero salen del stock vendible. Bodega y administración |
| `POST /api/v1/entradas` | Registrar mercadería recibida: proveedor, fecha y líneas (producto existente o nuevo, cantidad, costo unitario). Bodega y administración |
| `GET /api/v1/entradas/{id}` | Ver una entrada con sus líneas, quién la registró y cuándo. Bodega y administración |
| `GET /api/v1/ventas` | Listar las ventas del negocio por páginas (`pagina`, `por_pagina`), de la más reciente a la más antigua, sin sus líneas. Filtros opcionales: `estado` y `fecha` (día en hora de Costa Rica). Caja y administración |
| `POST /api/v1/ventas` | Abrir una venta en efectivo: líneas con producto y cantidad (el precio sale del catálogo). Descuenta el stock. Caja y administración |
| `GET /api/v1/ventas/{id}` | Ver una venta con sus líneas, total, cajero y estado. Caja y administración |
| `PUT /api/v1/ventas/{id}` | Reemplazar las líneas de una venta abierta; el stock se ajusta por la diferencia. Caja y administración |
| `POST /api/v1/ventas/{id}/cobrar` | Cerrar la venta como cobrada en efectivo; ya no se puede modificar. Caja y administración |
| `POST /api/v1/ventas/{id}/anular` | Anular una venta abierta o cobrada y devolver sus unidades al stock. Caja y administración |

### Stock y trazabilidad

Cada entrada de mercadería se guarda en una sola transacción: crea los productos nuevos, bloquea la fila de cada producto con `SELECT ... FOR UPDATE` antes de sumar su stock y deja una fila en `movimientos_inventario` con el tipo, la cantidad, el stock que resultó, quién y cuándo. Si una línea falla, no se guarda nada. Un `CHECK` en la base de datos impide que el stock quede negativo.

El **stock vendible** es el stock menos las unidades marcadas como vencidas que siguen en el estante (`unidades_vencidas` con estado `activa`), y nunca es negativo. Un producto está agotado cuando su stock vendible es 0.

Cada línea de una entrada guarda el costo anterior del producto, y la respuesta muestra `costo_anterior`, `costo_unitario`, `diferencia_costo` y el `precio_venta` actual, para que la administración decida si cambia el precio con `PATCH /productos/{id}/precio` o lo mantiene. La entrada nunca cambia el precio por su cuenta. Todo cambio de costo o de precio (al crear, editar, recibir o cambiar el precio de un producto) deja una fila en `historial_precios` con el motivo, quién y cuándo.

### Ventas en efectivo y concurrencia

Una venta (`ventas` y `venta_lineas`) tiene tres estados: `abierta`, `cobrada` y `anulada`. El precio de cada línea se toma del catálogo en el momento de vender y se guarda en la línea; el cliente nunca lo envía. Una venta abierta se puede modificar; una cobrada ya no; una cobrada o abierta se puede anular, y una anulada es definitiva.

Las unidades salen del stock al abrir la venta y vuelven al anularla. `DatabaseSaleRepository::save` hace todo en una transacción: bloquea la fila de la venta y la de cada producto con `SELECT ... FOR UPDATE` (siempre en el orden de los ids, para que dos ventas con los mismos productos no se bloqueen entre sí), calcula la diferencia entre lo que la venta tenía y lo que tiene ahora, y deja una fila en `movimientos_inventario` (`venta` o `anulacion`). Solo se venden las unidades vendibles (stock menos unidades vencidas en el estante). Si alguna línea no alcanza, responde `409` en formato RFC 9457 con el producto y las unidades disponibles, y no se guarda nada. Si dos cajas venden la última unidad, la segunda espera el bloqueo, ve que ya no queda y recibe el 409; el `CHECK (stock >= 0)` de la base de datos es la última red de seguridad.

### Control de edad en la venta de licor

Si alguna línea de una venta es de un producto con `es_licor = true`, la venta necesita la cédula del comprador (`cedula_comprador`, en el cuerpo de `POST` y de `PUT /ventas`). Sin ella la venta no se completa: responde `422` con el motivo y no se toca el stock. La regla vive en la entidad `Sale`, así que vale al abrir la venta y al modificarla; al modificarla sin mandar la cédula se conserva la que ya estaba registrada. La cédula se guarda solo con dígitos (de 9 a 12) en `ventas.cedula_comprador` y la revisa a mano el cajero: nunca se valida contra un servicio externo. Los datos de prueba y de demostración usan solo cédulas ficticias.

### Unidades vencidas y aviso en caja

`POST /productos/{id}/vencidos` guarda en `unidades_vencidas` la cantidad y el motivo, con quién la marcó, en estado `activa`. Las unidades marcadas siguen contando en el `stock`, pero salen del stock vendible (`stock - unidades vencidas activas`). No hace falta que el producto tenga fecha de vencimiento: la bodega marca lo que encuentra vencido en el estante. El repositorio bloquea la fila del producto (`FOR UPDATE`, el mismo bloqueo que usa una venta) y responde `409` si se intentan marcar más unidades de las que quedan sin marcar. Mientras la venta está abierta, su respuesta trae `advertencias`: un aviso `unidades_vencidas` por cada producto de las líneas que tenga unidades vencidas en el estante. El aviso no bloquea la venta: las demás unidades se pueden vender y solo se rechaza (`409`) pedir más de las vendibles.

### Horario de atención del negocio

El módulo `Negocios/OpeningHours` guarda cuándo atiende cada negocio. El horario se escribe por día (`lunes` … `domingo`) y cada día tiene hasta 3 tramos `{abre, cierra}` en formato `HH:MM`, así que un negocio puede cerrar al mediodía o un día completo (día sin tramos). Cada tramo es un registro de `horarios_negocio`, con Row Level Security y restricciones `CHECK` (día de 1 a 7 y cierre posterior a apertura). `PUT /negocio/horario` reemplaza el horario completo; un día que no se envía queda cerrado. La respuesta de ambas rutas trae los siete días, `abierto_ahora` y `zona_horaria`.

Las reglas viven en el dominio (`WeeklySchedule`, `TimeSlot`, `TimeOfDay`, `Weekday`) y no usan Laravel: un tramo abre en su hora de apertura y cierra en su hora de cierre (a las 20:00 en punto ya está cerrado), los tramos de un mismo día no se traslapan y la hora se lee en `America/Costa_Rica`, sin importar la zona horaria del servidor. El instante actual entra como parámetro (`now()` del controlador), igual que en la ruta de salud, así las pruebas fijan la hora con `travelTo`. Los datos de demostración traen el horario real del Minisúper Teresita (lunes a viernes 7:00 a 20:00, sábado y domingo 7:00 a 18:00) y uno ficticio para La Esquina, que cierra los domingos. El horario del personal no se administra: la ficha del proyecto lo deja fuera.

### Archivos

Las fotos y los logos se aceptan solo en JPG, PNG o WebP de hasta 2 MB. La regla `mimes` revisa el contenido real del archivo, no solo la extensión. Se guardan en el disco privado `local` (`storage/app/private`), nunca en `public/`, bajo `negocios/{id}/productos/{producto}/` y `negocios/{id}/logo/`. El nombre es el hash SHA-256 del contenido, nunca el nombre original. Cada producto tiene su propia carpeta, para que dos productos con la misma imagen no compartan el archivo y reemplazar la foto de uno no borre la del otro. Solo se descargan por las rutas de arriba, que exigen token y pasan por el aislamiento por negocio: la foto de un producto de otro negocio responde 404 aunque el archivo exista.
| `GET /up` | Ruta de salud del framework |

### Documentación de la API (Scramble)

La API se documenta sola con [Scramble](https://scramble.dedoc.co) (`dedoc/scramble`), a partir de las rutas, los Form Requests y los Resources:

| Qué | Dónde |
|---|---|
| Documentación interactiva | `GET /docs/api` (abre sin token; no tiene datos de ningún negocio) |
| Documento OpenAPI en vivo | `GET /docs/api.json` |
| Documento exportado | `api/openapi/v1.json`, para generar los tipos de TypeScript del cliente web |

Para volver a exportarlo después de cambiar endpoints:

```
docker compose exec api php artisan scramble:export
```

- **Qué se documenta:** solo las rutas bajo `/api/v1` (`api_path` en `config/scramble.php`). La seguridad (bearer token) se deduce del middleware `auth:sanctum`.
- **Errores:** Scramble infiere los `422` de los Form Requests, pero no sigue las excepciones de dominio que lanzan los casos de uso. `Src\Shared\Infrastructure\OpenApi\ProblemResponsesTransformer` agrega a cada endpoint los `401`, `403`, `404`, `409` y `429` que corresponden, con el esquema `Problema` (RFC 9457).
- **Tipos:** donde Scramble no puede inferir la forma de una respuesta (listas armadas con `array_map` o con `JsonResponse`), se declara con `@return` en `toArray()` de los Resources o con `@response` en el método del controlador.
- **Advertencias conocidas de `scramble:analyze`:** tres reglas de validación consultan el negocio de la cuenta (`unique`/`exists` filtradas por `negocio_id`). Al generar la documentación no hay cuenta autenticada, así que Scramble salta esas reglas; las demás reglas del mismo campo y de la petición sí quedan documentadas.
- **Prueba:** `OpenApiContractTest` falla si una ruta de `/api/v1` queda fuera del documento.

## Pruebas

| Carpeta | Alcance |
| --- | --- |
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. Usan dobles de los puertos de `tests/Support`. |
| `tests/Feature` | Endpoints y adaptadores. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos. |

Las pruebas se ejecutan con Pest (`vendor/bin/pest`), que también corre las pruebas escritas como clases de PHPUnit.

Las pruebas de `tests/Feature` usan PostgreSQL (base `minisuper_testing`), porque el aislamiento depende de Row Level Security. Migran con el dueño del esquema (`RefreshDatabaseAsOwner`) y consultan con el rol de la aplicación, como en producción. `tests/Feature/Tenancy` demuestra que un negocio no puede leer, editar ni borrar recursos de otro cambiando el ID.

### Pruebas de la ruta crítica

- **Fábricas:** los negocios, usuarios y productos se arman con sus factories de Eloquent; las ventas, que no tienen modelo, con `tests/Support/Factories/SaleFactory.php` (`SaleFactory::new()->for($negocio, $cajero)->line($producto, 2)->paid()->create()`). La fábrica no toca el stock.
- **Aislamiento:** `tests/Feature/Tenancy/StaffIsolationTest.php` recorre todos los endpoints que reciben un ID (productos, entradas, ventas y vencidos) con una cuenta administradora de otro negocio. Cada uno responde 404 y los datos del otro negocio quedan intactos. También comprueba las listas, el perfil y el camino inverso.
- **Concurrencia:** `LastUnitRaceTest` cubre las ventas simultáneas y `ExpiredUnitsRaceTest` el cruce entre marcar vencidos y vender: ambos bloquean la fila del producto con `FOR UPDATE`, así que lo vendido más lo marcado nunca supera el stock.
- **Flujo completo:** `tests/Feature/CriticalPathTest.php` hace entrada de mercadería, marcado de vencidos, venta con advertencia, cobro y anulación, y comprueba la disponibilidad en cada paso. Incluye la venta de licor con cédula.
